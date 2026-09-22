<?php
/**
 * Parses a raw text export of a Google Business reviews widget/page
 * (the kind of text you get by selecting all the text on a Google Maps
 * "avis" panel and copy-pasting it) into structured review rows.
 *
 * This targets the French Google Maps/Search reviews UI text pattern:
 *   "<Nom>Avis deGoogle<note>/5 · [Modifié ]il y a <ancienneté>[<texte>][Visité en <mois>[ <année>]]"
 * repeated once per review, with no reliable separator between one
 * review's trailing content and the next review's author name — this
 * class does its best to find that boundary using a name-shaped-word
 * heuristic, and it will not be perfect on every export. Reviews it
 * can't confidently split get a best-effort author name; nothing is
 * ever invented — every character of review text comes from the pasted
 * input.
 */
class GoogleReviewsParser
{
    private const MONTHS = [
        'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    private const PARTICLES = ['de', 'du', 'le', 'la', 'van', 'von', 'des', 'di'];

    /**
     * @return array<int, array{name:string, rating:int, age:string, visited:?string, text:string}>
     */
    public static function parse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // Normalize the two ways Google's own markup separates the
        // "Avis de" label from the "Google" source name.
        $raw = preg_replace('/Avis\s+de\s+Google/u', 'Avis deGoogle', $raw);

        $parts = explode('Avis deGoogle', $raw);
        if (count($parts) < 2) {
            return [];
        }

        // Everything before the first split is page chrome (widget
        // header, "Ajouter un avis" button, etc.) ending in the first
        // reviewer's name — take the last line-ish chunk of it.
        $header = array_shift($parts);
        $firstName = self::guessTrailingName($header);

        $months = implode('|', self::MONTHS);
        $reviews = [];
        $currentName = $firstName === '' ? 'Client Google' : $firstName;

        foreach ($parts as $chunk) {
            if (!preg_match('/^(\d)\/5\s*·\s*(Modifié\s+)?il y a ([a-zà-ÿ0-9 ]+?)(ans|an|mois)/u', $chunk, $m)) {
                continue;
            }

            $rating = (int) $m[1];
            $age = 'il y a ' . trim($m[3]) . ' ' . $m[4];
            $rest = mb_substr($chunk, mb_strlen($m[0]));

            $visited = null;
            $nextName = '';

            if (preg_match('/Visité en (' . $months . ')(\s+(\d{2,4}))?/u', $rest, $vm, PREG_OFFSET_CAPTURE)) {
                $charOffset = self::byteOffsetToCharOffset($rest, $vm[0][1]);
                $text = mb_substr($rest, 0, $charOffset);
                $visited = $vm[1][0] . ((isset($vm[3]) && $vm[3][0] !== '') ? ' ' . self::normalizeYear($vm[3][0]) : '');
                $tailStart = $charOffset + mb_strlen($vm[0][0]);
                $nextName = trim(mb_substr($rest, $tailStart));
            } else {
                [$text, $nextName] = self::splitTextAndTrailingName($rest);
            }

            $reviews[] = [
                'name' => $currentName,
                'rating' => $rating,
                'age' => $age,
                'visited' => $visited,
                'text' => self::cleanText($text),
            ];

            $currentName = $nextName === '' ? 'Client Google' : $nextName;
        }

        return $reviews;
    }

    private static function normalizeYear(string $year): string
    {
        if (mb_strlen($year) === 2) {
            return '20' . $year;
        }
        return $year;
    }

    private static function byteOffsetToCharOffset(string $haystack, int $byteOffset): int
    {
        return mb_strlen(substr($haystack, 0, $byteOffset));
    }

    private static function guessTrailingName(string $s): string
    {
        // The widget header commonly ends in "...Ajouter un avis<Nom>" —
        // strip that known chrome first since it defeats the generic
        // capitalized-word heuristic ("avis" is lowercase and glues to
        // the name with no space).
        if (preg_match('/Ajouter un avis(.*)$/us', $s, $m)) {
            $tail = trim($m[1]);
            if ($tail !== '') {
                return $tail;
            }
        }

        [, $name] = self::splitTextAndTrailingName($s);
        return $name;
    }

    /**
     * Best-effort split of a chunk with no "Visité en" marker into
     * [leading review text, trailing next-reviewer name], by walking
     * backwards from the end and keeping capitalized / particle words.
     *
     * @return array{0:string,1:string}
     */
    private static function splitTextAndTrailingName(string $s): array
    {
        $s = trim($s);
        if ($s === '') {
            return ['', ''];
        }

        $words = preg_split('/ /u', $s);
        $i = count($words);

        while ($i > 0) {
            $w = trim($words[$i - 1]);
            if ($w === '') {
                $i--;
                continue;
            }
            $core = preg_replace("/[^\\p{L}\\p{N}'-]/u", '', $w);
            if ($core === '') {
                break;
            }
            $isTitleCase = (bool) preg_match('/^\p{Lu}[\p{L}0-9\'-]*$/u', $core);
            $isAllCapsShort = mb_strtoupper($core, 'UTF-8') === $core && mb_strlen($core) <= 15 && preg_match('/\p{L}/u', $core);
            $isParticle = in_array(mb_strtolower($core, 'UTF-8'), self::PARTICLES, true);

            if ($isTitleCase || ($isAllCapsShort && $isTitleCase) || $isParticle) {
                $i--;
                continue;
            }
            break;
        }

        $nameWords = array_slice($words, $i);
        $textWords = array_slice($words, 0, $i);

        return [trim(implode(' ', $textWords)), trim(implode(' ', $nameWords))];
    }

    private static function cleanText(string $text): string
    {
        $text = preg_replace('/([.!?])(\p{Lu})/u', '$1 $2', $text);
        $text = preg_replace('/(\p{Ll})(\p{Lu})/u', '$1 $2', $text);
        return trim($text);
    }

    /**
     * @param array<int, array{rating:int}> $reviews
     * @return array{rating:float, count:int, best:int, worst:int}
     */
    public static function computeAggregate(array $reviews): array
    {
        $count = count($reviews);
        if ($count === 0) {
            return ['rating' => 0.0, 'count' => 0, 'best' => 5, 'worst' => 1];
        }

        $sum = 0;
        $best = 0;
        $worst = 5;
        foreach ($reviews as $r) {
            $sum += $r['rating'];
            $best = max($best, $r['rating']);
            $worst = min($worst, $r['rating']);
        }

        return [
            'rating' => round($sum / $count, 1),
            'count' => $count,
            'best' => $best,
            'worst' => $worst,
        ];
    }
}
