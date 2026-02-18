# Documentation — EcoRide

Ce dossier regroupe la documentation et les livrables du projet EcoRide.

## Arborescence

- `gestion_projet/`  
  Organisation et suivi du projet : cahier des charges, réflexion initiale, user story mapping, et une synthèse PDF.  
  **Règle : les liens sont centralisés dans `gestion_projet/liens_gestion_projet/` (kanban + user story mapping).**

- `interface/`  
  Charte graphique, maquettes et éléments liés à l’interface (UI).

- `modelisation_donnees/`  
  Modélisation, schémas et explications des choix (données et logique métier).

- `manuel_utilisation/`  
  Manuel d’utilisation de l’application (PDF).

- `ressources/`  
  Fichiers complémentaires (exports routes, capture Mailhog).

- `sql/`  
  Scripts SQL du projet (schéma, données de démonstration, requêtes de vérification).

- `uml/`  
  Diagrammes UML et explications associées.

- `deploiement/`
  Documentation et justification du déploiement de l'application

## Points d’entrée conseillés

1. **Manuel d’utilisation** : `manuel_utilisation/`
2. **Base de données** : `sql/` puis `modelisation_donnees/`
3. **Interface** : `interface/`
4. **Gestion de projet** : `gestion_projet/` (liens dans `gestion_projet/liens_gestion_projet/`)
5. **UML** : `uml/`
6. **Deploiement** : `deploiement_ecoride_sur_vps/

## Notes

- Les fichiers PDF sont des livrables prêts à être consultés.
- Les scripts SQL sont numérotés pour indiquer l’ordre d’exécution (voir `docs/sql/README.md`).
- Les liens (kanban + user story mapping) sont centralisés dans `gestion_projet/liens_gestion_projet/`
