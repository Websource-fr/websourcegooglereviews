# Changelog

## 1.3.0
- Compatibilité thèmes : détection du thème actif (nom + parent) pour Classic, Hummingbird (PrestaShop 9) et Warehouse ; thème inconnu = comportement inchangé.
- Le badge de note de la fiche produit s'affiche aussi sous Classic et Hummingbird (ces thèmes n'appellent pas `displayProductRating` : le module utilise `displayProductAdditionalInfo`). Script de mise à jour `upgrade-1.3.0.php`.
- Bloc d'avis de bas de fiche produit : variante de balisage pour Classic / Hummingbird, classes `ws-theme-<thème>`, couleurs de liens forcées sous Hummingbird.
- Encart « Besoin d'aller plus loin ? » (accompagnement Websource) dans la page de configuration, visible uniquement des super-administrateurs, masquable 30 jours. Au clic, le nom du module, sa version, la version de PrestaShop et l'adresse du site sont transmis dans l'URL.
- Documentation corrigée : le module n'émet pas lui-même de JSON-LD `AggregateRating` ; il expose les valeurs (`WebsourceGooglereviews::getAggregate()`) à intégrer dans le schéma du thème.

## 1.2.1
- Correctif d'une phrase tronquée quand `PS_SHOP_NAME` contient un long slogan.

## 1.2.0
- Repli automatique sur les fiches produit sans avis propre.
