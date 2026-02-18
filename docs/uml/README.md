# UML — Projet EcoRide

Ce dossier regroupe les diagrammes UML réalisés pour le projet EcoRide.

---

## Diagrammes livrables officiels

### Diagramme de cas d’utilisation

* `diagramme_cas_utilisation_ecoride.pdf`
* Représente les acteurs :
  * Visiteur
  * Utilisateur (passager / chauffeur)
  * Employé
  * Administrateur
* Montre les interactions principales avec le système.

### Diagramme de séquence — Scénario principal

* `diagramme_de_sequence_scenario_principal.pdf`
* Décrit le fonctionnement global de l’application :
  * Recherche et consultation
  * Participation
  * Publication
  * Historique et actions métier
  * Modération employé

Ce diagramme reflète l’architecture réelle :

* Contrôleurs Symfony
* SessionUtilisateur (authentification)
* Services de persistance (PostgreSQL)
* JournalEvenements (MongoDB)
* API de géocodage externe

---

## Archives de travail

Le dossier `archives_brouillons/` contient :

* Les anciens diagrammes de séquence (01 à 09)
* Les diagrammes d’activités
* Les versions intermédiaires de conception

Ces fichiers correspondent aux phases de réflexion et ne font pas partie des livrables finaux.

---

## Objectif

Les diagrammes livrés permettent :

* De comprendre l’architecture fonctionnelle
* De visualiser les interactions système
* De justifier les choix techniques réalisés
