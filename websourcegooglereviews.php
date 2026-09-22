<?php
/**
 * Websource Google Reviews
 *
 * Displays a public "avis clients" page built from your store's real
 * customer reviews (Google Business Profile or any other source) and
 * exposes the resulting AggregateRating to the rest of the theme via
 * Configuration keys, so schema.org markup can reflect real, sourced
 * numbers instead of an invented rating.
 *
 * There is no free Google Places API for bulk review text, so this module
 * does not attempt to auto-fetch reviews from Google. Instead, the back
 * office screen (Modules > Websource Google Reviews > Configure) lets you:
 *   - set the aggregate rating/review count directly (fastest option), and/or
 *   - import individual reviews in bulk (one per line, "note|auteur|texte",
 *     optionally "note|auteur|texte|date"), and/or
 *   - add/edit/delete individual reviews one at a time.
 * Whenever at least one individual review exists, the aggregate is always
 * computed live from those rows (never out of sync); the manual aggregate
 * fields are only used as a fallback when you don't want to publish
 * individual review text at all.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/GoogleReviewsParser.php';

class WebsourceGooglereviews extends Module
{
    const TABLE = 'websource_google_review';

    const CFG_RATING = 'WEBSOURCEGOOGLEREVIEWS_RATING';
    const CFG_COUNT = 'WEBSOURCEGOOGLEREVIEWS_COUNT';
    const CFG_BEST = 'WEBSOURCEGOOGLEREVIEWS_BEST';
    const CFG_WORST = 'WEBSOURCEGOOGLEREVIEWS_WORST';
    const CFG_IMPORTED_AT = 'WEBSOURCEGOOGLEREVIEWS_IMPORTED_AT';
    const CFG_SLUG = 'WEBSOURCEGOOGLEREVIEWS_SLUG';
    const CFG_SOURCE_LABEL = 'WEBSOURCEGOOGLEREVIEWS_SOURCE_LABEL';

    public function __construct()
    {
        $this->name = 'websourcegooglereviews';
        $this->tab = 'front_office_features';
        $this->version = '1.2.1';
        $this->author = 'Websource';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Websource Google Reviews');
        $this->description = $this->l('Page publique d\'avis clients + AggregateRating réel pour les données structurées du site (SEO/GEO).');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->createTable()) {
            return false;
        }

        if (!$this->registerHook('moduleRoutes')) {
            return false;
        }

        if (!$this->registerHook('displayHeader')
            || !$this->registerHook('displayProductRating')
            || !$this->registerHook('displayFooterProduct')) {
            return false;
        }

        Configuration::updateValue(self::CFG_SLUG, 'avis-clients');
        Configuration::updateValue(self::CFG_SOURCE_LABEL, 'Google');

        // Backward-compat / convenience: if a data/reviews.json file is
        // bundled (not the case in the public distribution), import it once.
        $this->importReviewsFromDataFile();

        return true;
    }

    public function uninstall()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');

        Configuration::deleteByName(self::CFG_RATING);
        Configuration::deleteByName(self::CFG_COUNT);
        Configuration::deleteByName(self::CFG_BEST);
        Configuration::deleteByName(self::CFG_WORST);
        Configuration::deleteByName(self::CFG_IMPORTED_AT);
        Configuration::deleteByName(self::CFG_SLUG);
        Configuration::deleteByName(self::CFG_SOURCE_LABEL);

        return parent::uninstall();
    }

    private function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_websource_google_review` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `author` VARCHAR(128) NOT NULL,
            `rating` TINYINT UNSIGNED NOT NULL,
            `age_text` VARCHAR(64) DEFAULT NULL,
            `visited_text` VARCHAR(64) DEFAULT NULL,
            `review_text` TEXT DEFAULT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_websource_google_review`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Optional one-time import from data/reviews.json if that file is
     * present next to this class (used for a pre-populated distribution;
     * the public module ships without it — reviews are added from the
     * back office screen instead).
     */
    private function importReviewsFromDataFile()
    {
        $path = dirname(__FILE__) . '/data/reviews.json';
        if (!file_exists($path)) {
            return false;
        }

        $reviews = json_decode(file_get_contents($path), true);
        if (!is_array($reviews) || empty($reviews)) {
            return false;
        }

        $rows = [];
        foreach ($reviews as $review) {
            $rows[] = [
                'author' => $review['name'],
                'rating' => (int) $review['rating'],
                'age_text' => $review['age'] ?? null,
                'visited_text' => $review['visited'] ?? null,
                'review_text' => $review['text'] ?? null,
            ];
        }

        $this->replaceAllReviews($rows);

        return true;
    }

    public function hookModuleRoutes($params)
    {
        $slug = Configuration::get(self::CFG_SLUG) ?: 'avis-clients';

        return [
            'module-websourcegooglereviews-avis' => [
                'controller' => 'avis',
                'rule' => $slug,
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'websourcegooglereviews',
                ],
            ],
        ];
    }

    /**
     * Replaces the whole review table content (used by bulk import).
     * @param array<int, array{author:string, rating:int, age_text:?string, visited_text:?string, review_text:?string}> $rows
     */
    private function replaceAllReviews(array $rows)
    {
        Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . self::TABLE . '`');

        $position = 0;
        foreach ($rows as $row) {
            Db::getInstance()->insert(self::TABLE, [
                'author' => pSQL($row['author']),
                'rating' => max(1, min(5, (int) $row['rating'])),
                'age_text' => !empty($row['age_text']) ? pSQL($row['age_text']) : null,
                'visited_text' => !empty($row['visited_text']) ? pSQL($row['visited_text']) : null,
                'review_text' => !empty($row['review_text']) ? pSQL($row['review_text'], true) : null,
                'position' => $position++,
            ]);
        }

        Configuration::updateValue(self::CFG_IMPORTED_AT, date('Y-m-d'));
    }

    /**
     * Generic parser for a simple, reliable bulk-paste format — one review
     * per line: "note|auteur|texte" or "note|auteur|texte|visite". Accepts
     * "|" or ";" as the separator. This intentionally does NOT try to
     * parse Google's own review-widget HTML/text (too fragile across
     * languages/locales/DOM changes to ship as a generic tool) — copy your
     * reviews into this format from wherever they're exported.
     *
     * @return array<int, array{author:string, rating:int, age_text:?string, visited_text:?string, review_text:?string}>
     */
    public static function parseBulkText($text)
    {
        $rows = [];
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $text));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $sep = (strpos($line, '|') !== false) ? '|' : ';';
            $parts = array_map('trim', explode($sep, $line));

            if (count($parts) < 3 || !is_numeric($parts[0])) {
                continue;
            }

            $rows[] = [
                'author' => $parts[1] !== '' ? $parts[1] : 'Client',
                'rating' => (int) round((float) $parts[0]),
                'age_text' => null,
                'visited_text' => isset($parts[3]) && $parts[3] !== '' ? $parts[3] : null,
                'review_text' => isset($parts[2]) && $parts[2] !== '' ? $parts[2] : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array{rating: float, count: int, best: int, worst: int, imported_at: ?string, source: string}
     */
    public static function getAggregate()
    {
        $row = Db::getInstance()->getRow(
            'SELECT COUNT(*) as cnt, AVG(`rating`) as avg_rating, MAX(`rating`) as max_rating, MIN(`rating`) as min_rating
             FROM `' . _DB_PREFIX_ . self::TABLE . '`'
        );

        if ($row && (int) $row['cnt'] > 0) {
            return [
                'rating' => round((float) $row['avg_rating'], 1),
                'count' => (int) $row['cnt'],
                'best' => (int) $row['max_rating'],
                'worst' => (int) $row['min_rating'],
                'imported_at' => Configuration::get(self::CFG_IMPORTED_AT),
                'source' => Configuration::get(self::CFG_SOURCE_LABEL) ?: 'Google',
            ];
        }

        return [
            'rating' => (float) Configuration::get(self::CFG_RATING),
            'count' => (int) Configuration::get(self::CFG_COUNT),
            'best' => (int) Configuration::get(self::CFG_BEST) ?: 5,
            'worst' => (int) Configuration::get(self::CFG_WORST) ?: 1,
            'imported_at' => Configuration::get(self::CFG_IMPORTED_AT),
            'source' => Configuration::get(self::CFG_SOURCE_LABEL) ?: 'Google',
        ];
    }

    /**
     * @return array<int, array{id_websource_google_review:int, author:string, rating:int, age_text:?string, visited_text:?string, review_text:?string}>
     */
    public static function getReviews()
    {
        return Db::getInstance()->executeS(
            'SELECT `id_websource_google_review`, `author`, `rating`, `age_text`, `visited_text`, `review_text`
             FROM `' . _DB_PREFIX_ . self::TABLE . '`
             ORDER BY `position` ASC'
        );
    }

    public static function getPageUrl()
    {
        $slug = Configuration::get(self::CFG_SLUG) ?: 'avis-clients';
        return Context::getContext()->shop->getBaseURL(true) . $slug;
    }

    // ---------------------------------------------------------------
    // Product-page fallback (real store reviews shown when a product has
    // no reviews of its own via iqitreviews). Assigns global Smarty vars
    // early (displayHeader) so the theme's iqitreviews template overrides
    // can use them later in the same request — never claims these reviews
    // are about the specific product, only about the store, to avoid the
    // kind of mismatched-attribution problem already fixed on the blog.
    // ---------------------------------------------------------------

    public function hookDisplayHeader($params)
    {
        if (!($this->context->controller instanceof ProductControllerCore)) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'ws-avis-css-pdp',
            'modules/' . $this->name . '/views/css/avis.css',
            ['media' => 'all', 'priority' => 150]
        );

        $this->context->smarty->assign([
            'ws_pdp_aggregate' => self::getAggregate(),
            'ws_pdp_review_pool' => self::getCompleteReviews(),
            'ws_pdp_page_url' => self::getPageUrl(),
        ]);
    }

    /**
     * Reviews whose text isn't a truncated preview (no trailing "… Plus"
     * from a scraped source) — safe to show as a standalone full quote.
     *
     * @return array<int, array>
     */
    public static function getCompleteReviews()
    {
        return array_values(array_filter(self::getReviews(), function ($review) {
            $text = trim((string) $review['review_text']);

            return $text !== ''
                && substr($text, -4) !== 'Plus'
                && strpos($text, '…') === false
                && (int) $review['rating'] >= 4;
        }));
    }

    /**
     * True when this product already has at least one approved review of
     * its own (iqitreviews' table, read-only — works whether or not that
     * module is currently active). Used to decide whether to show the
     * fallback at all: a product with real reviews of its own never needs
     * the store-wide fallback.
     */
    private static function productHasOwnReview($idProduct)
    {
        if (!Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "iqitreviews_products'")) {
            return false;
        }

        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'iqitreviews_products`
             WHERE id_product = ' . (int) $idProduct . ' AND status = 1'
        );
    }

    /**
     * Compact star-rating badge near the product title — single product
     * page only (never on a listing/category grid, so the same badge
     * doesn't repeat on every miniature).
     */
    public function hookDisplayProductRating($params)
    {
        $idProduct = isset($params['product']['id_product']) ? (int) $params['product']['id_product'] : 0;
        if (!$idProduct || self::productHasOwnReview($idProduct)) {
            return '';
        }

        $aggregate = self::getAggregate();
        if ($aggregate['count'] <= 0) {
            return '';
        }

        $this->context->smarty->assign([
            'ws_pdp_aggregate' => $aggregate,
            'ws_pdp_page_url' => self::getPageUrl(),
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/hook/product-rating-fallback.tpl');
    }

    /**
     * Main reviews block in the product page footer, shown only when the
     * product has no review of its own.
     */
    public function hookDisplayFooterProduct($params)
    {
        $idProduct = isset($params['product']['id_product']) ? (int) $params['product']['id_product'] : 0;
        if (!$idProduct || self::productHasOwnReview($idProduct)) {
            return '';
        }

        $pool = self::getCompleteReviews();
        if (empty($pool)) {
            return '';
        }

        $this->context->controller->registerStylesheet(
            'ws-avis-css-pdp',
            'modules/' . $this->name . '/views/css/avis.css',
            ['media' => 'all', 'priority' => 150]
        );

        $poolCount = count($pool);
        $start = $idProduct % $poolCount;
        $picked = [];
        for ($i = 0; $i < min(3, $poolCount); $i++) {
            $picked[] = $pool[($start + $i) % $poolCount];
        }

        $this->context->smarty->assign([
            'ws_pdp_aggregate' => self::getAggregate(),
            'ws_pdp_picked_reviews' => $picked,
            'ws_pdp_page_url' => self::getPageUrl(),
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/hook/product-reviews-fallback.tpl');
    }

    // ---------------------------------------------------------------
    // Back office
    // ---------------------------------------------------------------

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('wsgr_save_aggregate')) {
            Configuration::updateValue(self::CFG_RATING, (float) str_replace(',', '.', Tools::getValue('wsgr_rating')));
            Configuration::updateValue(self::CFG_COUNT, (int) Tools::getValue('wsgr_count'));
            Configuration::updateValue(self::CFG_BEST, (int) Tools::getValue('wsgr_best') ?: 5);
            Configuration::updateValue(self::CFG_WORST, (int) Tools::getValue('wsgr_worst') ?: 1);
            Configuration::updateValue(self::CFG_SOURCE_LABEL, pSQL(Tools::getValue('wsgr_source_label')) ?: 'Google');
            Configuration::updateValue(self::CFG_IMPORTED_AT, date('Y-m-d'));
            $output .= $this->displayConfirmation($this->l('Note globale enregistrée.'));
        }

        if (Tools::isSubmit('wsgr_paste_import')) {
            $rows = GoogleReviewsParser::parse((string) Tools::getValue('wsgr_paste_text'));
            if (empty($rows)) {
                $output .= $this->displayError($this->l('Aucun avis détecté dans le texte collé. Vérifiez que vous avez bien copié tout le panneau d\'avis Google (le texte doit contenir "Avis de" et "Google").'));
            } else {
                $mapped = array_map(function ($r) {
                    return [
                        'author' => $r['name'],
                        'rating' => $r['rating'],
                        'age_text' => $r['age'],
                        'visited_text' => $r['visited'],
                        'review_text' => $r['text'],
                    ];
                }, $rows);
                $this->replaceAllReviews($mapped);
                $output .= $this->displayConfirmation(sprintf(
                    $this->l('%d avis importés depuis le texte collé (remplace les avis existants). Relisez la liste ci-dessous : le découpage auteur/texte est automatique et peut nécessiter une correction ponctuelle (bouton supprimer, puis ajout manuel du bon avis si besoin).'),
                    count($rows)
                ));
            }
        }

        if (Tools::isSubmit('wsgr_bulk_import')) {
            $rows = self::parseBulkText(Tools::getValue('wsgr_bulk_text'));
            if (empty($rows)) {
                $output .= $this->displayError($this->l('Aucune ligne valide détectée. Format attendu par ligne : note|auteur|texte'));
            } else {
                $this->replaceAllReviews($rows);
                $output .= $this->displayConfirmation(sprintf($this->l('%d avis importés (remplace les avis existants).'), count($rows)));
            }
        }

        if (Tools::isSubmit('wsgr_add_review')) {
            $rating = (int) Tools::getValue('wsgr_new_rating');
            $author = trim(Tools::getValue('wsgr_new_author'));
            $text = trim(Tools::getValue('wsgr_new_text'));
            if ($rating >= 1 && $rating <= 5 && $author !== '') {
                $existing = self::getReviews();
                Db::getInstance()->insert(self::TABLE, [
                    'author' => pSQL($author),
                    'rating' => $rating,
                    'age_text' => null,
                    'visited_text' => null,
                    'review_text' => $text !== '' ? pSQL($text, true) : null,
                    'position' => count($existing),
                ]);
                $output .= $this->displayConfirmation($this->l('Avis ajouté.'));
            } else {
                $output .= $this->displayError($this->l('Note (1-5) et auteur sont obligatoires.'));
            }
        }

        if (Tools::isSubmit('wsgr_delete_review')) {
            $id = (int) Tools::getValue('id_review');
            Db::getInstance()->delete(self::TABLE, '`id_websource_google_review` = ' . $id);
            $output .= $this->displayConfirmation($this->l('Avis supprimé.'));
        }

        return $output . $this->renderConfigForm();
    }

    private function renderConfigForm()
    {
        $aggregate = self::getAggregate();
        $reviews = self::getReviews();
        $pageUrl = self::getPageUrl();

        $reviewsRowsHtml = '';
        foreach ($reviews as $review) {
            $reviewsRowsHtml .= '<tr>'
                . '<td>' . str_repeat('&#9733;', (int) $review['rating']) . str_repeat('&#9734;', 5 - (int) $review['rating']) . '</td>'
                . '<td>' . htmlspecialchars($review['author'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="max-width:420px">' . htmlspecialchars(Tools::substr((string) $review['review_text'], 0, 160), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>'
                    . '<form method="post" style="margin:0" onsubmit="return confirm(\'' . $this->l('Supprimer cet avis ?') . '\');">'
                    . '<input type="hidden" name="id_review" value="' . (int) $review['id_websource_google_review'] . '">'
                    . '<button type="submit" name="wsgr_delete_review" class="btn btn-default btn-xs"><i class="icon-trash"></i></button>'
                    . '</form>'
                . '</td>'
                . '</tr>';
        }
        if (empty($reviews)) {
            $reviewsRowsHtml = '<tr><td colspan="4"><em>' . $this->l('Aucun avis individuel importé — la note globale ci-dessus est utilisée telle quelle.') . '</em></td></tr>';
        }

        $ratingDisplay = number_format($aggregate['rating'], 1, ',', '');

        return '
        <div class="panel">
          <div class="panel-heading"><i class="icon-star"></i> ' . $this->l('Websource Google Reviews') . '</div>
          <div class="panel-body">
            <p>' . sprintf($this->l('Page publique : %s — lien à ajouter vous-même dans votre menu ou votre pied de page si besoin.'), '<a href="' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '</a>') . '</p>
            <p><strong>' . $this->l('Note actuelle affichée sur le site') . ' :</strong> ' . $ratingDisplay . '/5 · ' . (int) $aggregate['count'] . ' ' . $this->l('avis') . ' (' . htmlspecialchars($aggregate['source'], ENT_QUOTES, 'UTF-8') . ')</p>
          </div>
        </div>

        <div class="panel">
          <div class="panel-heading"><i class="icon-cog"></i> ' . $this->l('1. Note globale (utilisée si aucun avis individuel n\'est importé)') . '</div>
          <div class="panel-body">
            <form method="post" class="form-horizontal">
              <div class="form-group">
                <label class="control-label col-lg-3">' . $this->l('Source (libellé affiché)') . '</label>
                <div class="col-lg-4"><input type="text" name="wsgr_source_label" class="form-control" value="' . htmlspecialchars($aggregate['source'], ENT_QUOTES, 'UTF-8') . '"></div>
              </div>
              <div class="form-group">
                <label class="control-label col-lg-3">' . $this->l('Note moyenne (ex: 4.8)') . '</label>
                <div class="col-lg-2"><input type="text" name="wsgr_rating" class="form-control" value="' . $ratingDisplay . '"></div>
              </div>
              <div class="form-group">
                <label class="control-label col-lg-3">' . $this->l('Nombre total d\'avis') . '</label>
                <div class="col-lg-2"><input type="number" name="wsgr_count" class="form-control" value="' . (int) $aggregate['count'] . '"></div>
              </div>
              <div class="form-group">
                <label class="control-label col-lg-3">' . $this->l('Note la plus basse / la plus haute') . '</label>
                <div class="col-lg-1"><input type="number" min="1" max="5" name="wsgr_worst" class="form-control" value="' . (int) $aggregate['worst'] . '"></div>
                <div class="col-lg-1"><input type="number" min="1" max="5" name="wsgr_best" class="form-control" value="' . (int) $aggregate['best'] . '"></div>
              </div>
              <div class="form-group">
                <div class="col-lg-offset-3 col-lg-9">
                  <button type="submit" name="wsgr_save_aggregate" class="btn btn-primary">' . $this->l('Enregistrer la note globale') . '</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="panel">
          <div class="panel-heading"><i class="icon-upload"></i> ' . $this->l('2. Coller un export Google (recommandé — le plus rapide)') . '</div>
          <div class="panel-body">
            <p>' . $this->l('Ouvrez la fiche de votre établissement sur Google (Maps ou Recherche), affichez le panneau "Avis", sélectionnez tout le texte du panneau (Ctrl+A / Cmd+A dans le panneau) et collez-le ci-dessous.') . '</p>
            <p><em>' . $this->l('Le découpage auteur / note / texte / date est automatique. Sur de gros volumes d\'avis, un petit nombre de lignes peut être mal séparé (avis sans date de visite affichée) — relisez la liste importée ci-dessous et corrigez au besoin avec les boutons supprimer / ajouter.') . '</em></p>
            <form method="post">
              <textarea name="wsgr_paste_text" class="form-control" rows="8" placeholder="' . $this->l('Collez ici tout le texte du panneau d\'avis Google...') . '"></textarea>
              <p class="help-block">' . $this->l('Ce remplace tous les avis actuellement importés.') . '</p>
              <button type="submit" name="wsgr_paste_import" class="btn btn-primary" onclick="return confirm(\'' . $this->l('Ceci remplace tous les avis existants. Continuer ?') . '\');">' . $this->l('Analyser et importer') . '</button>
            </form>
          </div>
        </div>

        <div class="panel">
          <div class="panel-heading"><i class="icon-table"></i> ' . $this->l('2bis. Ou : format texte personnalisé (avancé)') . '</div>
          <div class="panel-body">
            <p>' . $this->l('Un avis par ligne, séparateur "|" ou ";" : ') . '<code>note|auteur|texte</code> ' . $this->l('ou') . ' <code>note|auteur|texte|date de visite</code>.</p>
            <p><em>' . $this->l('Utile si vos avis viennent de Trustpilot, Avis Vérifiés, ou d\'un tableur — préparez une ligne par avis puis collez.') . '</em></p>
            <form method="post">
              <textarea name="wsgr_bulk_text" class="form-control" rows="6" placeholder="5|Marie D.|Super accueil, je recommande !|mars 2026&#10;4|Julien|Livraison rapide, bon service."></textarea>
              <p class="help-block">' . $this->l('Ce remplace tous les avis actuellement importés — utilisez l\'ajout unitaire ci-dessous pour ajouter un avis isolé sans tout réimporter.') . '</p>
              <button type="submit" name="wsgr_bulk_import" class="btn btn-default" onclick="return confirm(\'' . $this->l('Ceci remplace tous les avis existants. Continuer ?') . '\');">' . $this->l('Importer (remplace tout)') . '</button>
            </form>
          </div>
        </div>

        <div class="panel">
          <div class="panel-heading"><i class="icon-plus"></i> ' . $this->l('3. Ajouter un avis') . '</div>
          <div class="panel-body">
            <form method="post" class="form-inline">
              <select name="wsgr_new_rating" class="form-control">
                <option value="5">5 &#9733;</option>
                <option value="4">4 &#9733;</option>
                <option value="3">3 &#9733;</option>
                <option value="2">2 &#9733;</option>
                <option value="1">1 &#9733;</option>
              </select>
              <input type="text" name="wsgr_new_author" class="form-control" placeholder="' . $this->l('Auteur') . '">
              <input type="text" name="wsgr_new_text" class="form-control" style="width:340px" placeholder="' . $this->l('Texte de l\'avis (optionnel)') . '">
              <button type="submit" name="wsgr_add_review" class="btn btn-default">' . $this->l('Ajouter') . '</button>
            </form>
          </div>
        </div>

        <div class="panel">
          <div class="panel-heading"><i class="icon-list"></i> ' . $this->l('Avis importés') . ' (' . count($reviews) . ')</div>
          <div class="panel-body">
            <table class="table">
              <thead><tr><th>' . $this->l('Note') . '</th><th>' . $this->l('Auteur') . '</th><th>' . $this->l('Texte') . '</th><th></th></tr></thead>
              <tbody>' . $reviewsRowsHtml . '</tbody>
            </table>
          </div>
        </div>
        ';
    }
}
