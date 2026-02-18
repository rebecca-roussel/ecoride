# Ressources — EcoRide

Ce dossier regroupe des fichiers pratiques utilisés pour documenter le projet et faciliter la compréhension de son fonctionnement.

## Contenu

### routes.txt

Liste complète des routes de l’application (export de la commande Symfony `debug:router`), incluant les routes **GET** et **POST**.  
Utilité : repérer rapidement les endpoints disponibles et vérifier la cohérence entre pages et actions.

### routes_essentielles.txt

Liste synthétique des routes principales correspondant aux parcours applicatifs les plus courants.  
Utilité : support de navigation et point d’entrée rapide pour découvrir les fonctionnalités.

### capture_mailhog.png

Capture d’écran de l’interface MailHog montrant la réception d’un email envoyé par EcoRide en environnement de développement qui  preuve constitue une preuve visuelle du fonctionnement des emails automatiques (sans envoi vers une boîte mail réelle).

## Notes

- Ces fichiers sont des ressources de documentation : ils ne sont pas exécutés par l’application.

- Les exports de routes reflètent l’état du projet au moment de leur génération et peuvent évoluer dans le temps.
