# Websource Google Reviews

Module PrestaShop gratuit qui affiche une vraie page « Avis clients » sur
votre boutique, à partir de vos propres avis Google (ou de toute autre
source d'avis), et calcule une note moyenne (`AggregateRating`) **réelle**,
prête à être publiée dans les données structurées (schema.org / JSON-LD)
de votre thème — le levier de confiance le plus souvent absent des sites
e-commerce en matière de SEO/GEO. **Le module n'émet pas lui-même de
JSON-LD** : il fournit les valeurs, l'intégration au schéma de votre thème
est décrite plus bas.

Développé par [Websource](https://www.websource.fr), agence web
spécialisée PrestaShop et SEO.

## Pourquoi ce module

Un site marchand affiche presque toujours son adresse, ses produits, ses
avis... mais très rarement de vraies données structurées `AggregateRating`
sourcées sur de vrais avis. Résultat : Google, ChatGPT, Perplexity ou
Gemini n'ont aucun signal de confiance exploitable dans le JSON-LD de la
page, alors que le commerçant a peut-être 100+ avis 5 étoiles sur sa
fiche Google Business. Ce module comble ce manque, sans rien inventer :
vous importez vos vrais avis, le module s'occupe du reste.

## Fonctionnalités

- **Page publique `/avis-clients`** (URL personnalisable), listant vos
  avis individuels — note, auteur, texte, ancienneté.
- **Note moyenne réelle (`AggregateRating`)** calculée à partir de vos avis
  et exposée par `WebsourceGooglereviews::getAggregate()` : à brancher
  dans le JSON-LD de votre thème, par exemple dans votre schéma
  `Organization` — voir
  [Intégration au schéma global](#intégration-au-schéma-global-organization).
  Le module n'ajoute aucun JSON-LD de lui-même.
- **Compatible thèmes Classic, Hummingbird et Warehouse** (et thèmes
  enfants) : détection du thème, badge de note sur la fiche produit et
  bloc d'avis adaptés. Surcharge possible depuis le thème dans
  `themes/<thème>/modules/websourcegooglereviews/views/templates/`.
- Encart « accompagnement Websource » dans la page de configuration
  (super-administrateurs uniquement, masquable 30 jours).
- **Écran d'import en back office** (Modules > Websource Google Reviews
  > Configurer), trois façons d'alimenter vos avis :
  1. **Coller un export Google** — sélectionnez tout le texte du
     panneau d'avis sur votre fiche Google Maps/Recherche, collez-le :
     le module détecte automatiquement note, auteur, texte et date
     pour chaque avis.
  2. **Format texte personnalisé** (`note|auteur|texte|date`, un avis
     par ligne) — pratique pour importer depuis Trustpilot, Avis
     Vérifiés ou un tableur.
  3. **Ajout unitaire** — pour corriger ou compléter un avis après
     import automatique.
  - Une **note globale manuelle** (note moyenne + nombre d'avis) reste
    disponible en secours, si vous préférez ne publier aucun texte
    d'avis individuel.
- **Repli automatique sur les fiches produit** (depuis la v1.2.0) : un
  badge de note et un bloc d'avis s'affichent sur une fiche produit qui
  n'a pas encore reçu d'avis, en piochant dans votre pool d'avis
  magasin (uniquement les avis 4-5★ au texte complet, jamais tronqués)
  — toujours présentés comme des avis sur la boutique en général,
  jamais comme des avis du produit lui-même, et **sans** émettre de
  `Review`/`AggregateRating` au niveau produit dans le JSON-LD (un avis
  boutique attribué à un produit précis serait une donnée structurée
  trompeuse). Se désactive tout seul dès qu'un produit reçoit son
  propre avis (compatible avec le module `iqitreviews`, actif ou non).

## Installation

1. Téléchargez le fichier `.zip` de ce dépôt (ou clonez-le).
2. Dans votre back office PrestaShop : **Modules > Gestionnaire de
   modules > Importer un module**, sélectionnez le zip.
3. Une fois installé, allez dans **Modules > Websource Google Reviews >
   Configurer** pour importer vos avis (voir ci-dessus).
4. La page est immédiatement disponible à `https://votre-site.fr/avis-clients`
   (l'URL est configurable si besoin d'un futur écran de réglages —
   pour l'instant, modifiable via la clé de configuration
   `WEBSOURCEGOOGLEREVIEWS_SLUG`).
5. Ajoutez vous-même un lien vers cette page où vous le souhaitez (menu,
   pied de page...) — la plupart des thèmes PrestaShop stockent leurs
   listes de liens de pied de page en base (module de liens du thème),
   il n'y a pas de méthode universelle pour l'automatiser.

**Compatibilité :** PrestaShop 1.7 à 9.x. Module de style « classique »
(`extends Module`), testé en production sur PrestaShop 9.0.

## Intégration au schéma global (Organization)

Pour que l'`AggregateRating` apparaisse aussi dans le JSON-LD de VOTRE
entité `Organization`/`LocalBusiness` globale (celui de votre page
d'accueil, généralement dans le `<head>` de votre thème), ajoutez
manuellement, dans le bloc JSON-LD existant de votre thème, une
propriété `aggregateRating` alimentée par
`WebsourceGooglereviews::getAggregate()` :

```smarty
{assign var="wsgrAggregate" value=WebsourceGooglereviews::getAggregate()}
{if $wsgrAggregate.count > 0}
,"aggregateRating": {
  "@type": "AggregateRating",
  "ratingValue": "{$wsgrAggregate.rating}",
  "bestRating": "{$wsgrAggregate.best}",
  "worstRating": "{$wsgrAggregate.worst}",
  "reviewCount": "{$wsgrAggregate.count}"
}
{/if}
```

(Un appel de méthode statique dans un template Smarty nécessite que la
sécurité Smarty l'autorise sur votre installation — si ce n'est pas le
cas, copiez simplement les 4 valeurs affichées dans Modules > Websource
Google Reviews > Configurer directement dans votre template, en dur,
et mettez-les à jour après chaque nouvel import.)

Ce module ne modifie **jamais** vos fichiers de thème lui-même : cette
étape reste volontairement manuelle, chaque thème PrestaShop organisant
son JSON-LD différemment.

## Format de l'export Google (import automatique)

Le module reconnaît le texte tel qu'il apparaît quand on sélectionne et
copie le panneau d'avis d'une fiche Google (Maps ou Recherche), en
français. Le motif recherché est celui-ci, répété une fois par avis :

```
<Nom>Avis de Google<note>/5 · il y a <ancienneté><texte>Visité en <mois> [<année>]
```

Sur les avis **sans texte** et **sans date de visite affichée**, la
séparation entre la fin d'un avis et le nom du suivant repose sur une
heuristique (mots commençant par une majuscule = probablement un nom).
Sur un import de 100+ avis réels, cette heuristique s'est montrée fiable
sur environ 9 avis sur 10 ; les quelques avis mal découpés se corrigent
en quelques clics (bouton supprimer, puis ajout manuel) après import —
relisez toujours la liste importée avant de considérer l'import terminé.

**Limite connue :** ce parseur cible le texte en français de l'interface
Google. Un export dans une autre langue ne sera probablement pas
reconnu — utilisez dans ce cas le format texte personnalisé
(`note|auteur|texte|date`).

## Licence

Usage libre non commercial — voir [`LICENSE.md`](LICENSE.md). En
résumé : gratuit à utiliser et à modifier sur vos boutiques ou celles
de vos clients, y compris dans le cadre de prestations facturées ; pas
de revente du module lui-même.

## Support

Ce module est fourni tel quel, sans garantie. Pour une demande
d'évolution, un usage commercial (revente, intégration dans un pack
payant), ou une prestation d'installation/personnalisation :
[contact@websource.fr](mailto:contact@websource.fr) —
[www.websource.fr](https://www.websource.fr)
