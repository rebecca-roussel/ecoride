# Matrice fonctionnalités et traitements — EcoRide

Ce document relie les fonctionnalités prévues dans le dossier de conception aux traitements réellement présents dans le projet EcoRide.

L’objectif est de montrer que les parcours décrits dans la conception ont une traduction concrète dans l’application : routes Symfony, contrôleurs, services, templates Twig, scripts SQL ou preuves de vérification.

Cette matrice ne remplace pas les tests automatisés. Elle sert à montrer la traçabilité entre le besoin, le code et les preuves disponibles.

## 1. Sources de conception utilisées

Les fonctionnalités ont été définies à partir des documents et supports de conception du projet :

* cahier des charges EcoRide ;
* user story mapping ;
* diagramme de cas d’utilisation ;
* diagrammes de séquence ;
* maquettes des écrans ;
* manuel d’utilisation ;
* scripts SQL et requêtes de vérification.

Ces documents sont présents dans les dossiers :

* `docs/gestion_projet/` ;
* `docs/interface/` ;
* `docs/uml/` ;
* `docs/sql/` ;
* `docs/manuel_utilisation/`.

## 2. Principe de lecture

Chaque fonctionnalité est reliée à plusieurs éléments :

* une route Symfony ;
* un contrôleur ;
* un ou plusieurs services ;
* un template Twig quand la fonctionnalité affiche une page ;
* une preuve ou une vérification disponible.

## 3. Matrice de traçabilité

| Fonctionnalité prévue                  | Route Symfony                                                                        | Contrôleur                                                                | Service principal                                                                                   | Vue Twig / preuve                                                                                                         | État    |
| -------------------------------------- | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- | ------- |
| Afficher la page d’accueil             | `/`                                                                                  | `AccueilController.php`                                                   | Aucun service métier principal identifié                                                            | `templates/accueil/index.html.twig`, test `AccueilTest.php`                                                               | Couvert |
| Rechercher un covoiturage              | `/recherche`, `/resultats`                                                           | `RechercheController.php`, `ResultatsController.php`                      | `PersistanceCovoituragePostgresql.php`                                                              | `templates/recherche/index.html.twig`, `templates/resultats/index.html.twig`, maquettes recherche/résultats               | Couvert |
| Consulter les résultats de recherche   | `/resultats`                                                                         | `ResultatsController.php`                                                 | `PersistanceCovoituragePostgresql.php`                                                              | `templates/resultats/index.html.twig`                                                                                     | Couvert |
| Consulter le détail d’un covoiturage   | `/details`                                                                           | `DetailsController.php`                                                   | `PersistanceCovoituragePostgresql.php`                                                              | `templates/details/index.html.twig`                                                                                       | Couvert |
| Créer un compte                        | `/inscription`                                                                       | `InscriptionController.php`                                               | `PersistanceUtilisateurPostgresql.php`, `JournalEvenements.php`                                     | `templates/inscription/index.html.twig`                                                                                   | Couvert |
| Se connecter                           | `/connexion`                                                                         | `ConnexionController.php`                                                 | `PersistanceUtilisateurPostgresql.php`, `SessionUtilisateur.php`, `JournalEvenements.php`           | `templates/connexion/index.html.twig`                                                                                     | Couvert |
| Se déconnecter                         | `/deconnexion`                                                                       | `ConnexionController.php`                                                 | `SessionUtilisateur.php`                                                                            | Formulaire de déconnexion avec jeton CSRF                                                                                 | Couvert |
| Consulter le tableau de bord           | `/tableau-de-bord`                                                                   | `TableauDeBordController.php`                                             | `SessionUtilisateur.php`, `PersistanceUtilisateurPostgresql.php`                                    | `templates/tableau_de_bord/index.html.twig`, maquettes tableau de bord                                                    | Couvert |
| Gérer son profil                       | `/profil`                                                                            | `ProfilController.php`                                                    | `PersistanceUtilisateurPostgresql.php`, `SessionUtilisateur.php`                                    | `templates/profil/index.html.twig`                                                                                        | Couvert |
| Gérer ses rôles chauffeur/passager     | `/tableau-de-bord/roles`                                                             | `GererRolesController.php`                                                | `PersistanceUtilisateurPostgresql.php`, `SessionUtilisateur.php`                                    | `templates/roles/index.html.twig`                                                                                         | Couvert |
| Gérer ses voitures                     | `/espace/vehicules`                                                                  | `GererVehiculesController.php`                                            | `PersistanceVoiturePostgresql.php`, `JournalEvenements.php`, `SessionUtilisateur.php`               | `templates/vehicules/index.html.twig`, `templates/vehicules/ajouter.html.twig`                                            | Couvert |
| Publier un covoiturage                 | `/publier`                                                                           | `PublierCovoiturageController.php`                                        | `PersistanceCovoituragePostgresql.php`, `PersistanceVoiturePostgresql.php`, `JournalEvenements.php` | `templates/publier/index.html.twig`, diagramme de séquence publication                                                    | Couvert |
| Participer à un covoiturage            | `/participer/{id}`                                                                   | `ParticiperCovoiturageController.php`                                     | `PersistanceCovoituragePostgresql.php`, `SessionUtilisateur.php`, `JournalEvenements.php`           | Formulaire de participation dans `templates/details/index.html.twig`, scripts SQL de vérification places/crédits          | Couvert |
| Consulter son historique               | `/historique`                                                                        | `HistoriqueController.php`                                                | `PersistanceHistoriquePostgresql.php`, `SessionUtilisateur.php`                                     | `templates/historique/index.html.twig`                                                                                    | Couvert |
| Annuler une participation              | `/historique/annuler-participation`                                                  | `HistoriqueController.php`                                                | `PersistanceHistoriquePostgresql.php`, `EnvoiCourriels.php`, `JournalEvenements.php`                | `templates/historique/index.html.twig`, captures MailHog annulation                                                       | Couvert |
| Annuler un covoiturage                 | `/historique/annuler-covoiturage`                                                    | `HistoriqueController.php`                                                | `PersistanceHistoriquePostgresql.php`, `EnvoiCourriels.php`, `JournalEvenements.php`                | `templates/historique/index.html.twig`, captures MailHog annulation                                                       | Couvert |
| Démarrer un covoiturage                | `/historique/demarrer-covoiturage`                                                   | `HistoriqueController.php`                                                | `PersistanceHistoriquePostgresql.php`, `EnvoiCourriels.php`, `JournalEvenements.php`                | `templates/historique/index.html.twig`, captures MailHog validation                                                       | Couvert |
| Terminer un covoiturage                | `/historique/terminer-covoiturage`                                                   | `HistoriqueController.php`                                                | `PersistanceHistoriquePostgresql.php`, `EnvoiCourriels.php`, `JournalEvenements.php`                | `templates/historique/index.html.twig`, captures MailHog validation                                                       | Couvert |
| Déposer un avis après covoiturage      | `/historique/satisfaction/{id}`, `/historique/enregistrer-satisfaction`              | `HistoriqueController.php`                                                | `PersistanceHistoriquePostgresql.php`, `JournalEvenements.php`                                      | `templates/historique/satisfaction.html.twig`, scripts SQL modération avis                                                | Couvert |
| Déclarer un incident                   | `/historique/incident/{id}`, `/historique/declarer-incident`                         | `HistoriqueController.php`                                                | `PersistanceHistoriquePostgresql.php`, `JournalEvenements.php`                                      | `templates/historique/incident.html.twig`, scripts SQL incidents                                                          | Couvert |
| Modérer les avis                       | `/espace-employe`, `/espace-employe/avis/valider`, `/espace-employe/avis/refuser`    | `EspaceEmployeController.php`                                             | `PersistanceEmployePostgresql.php`, `SessionUtilisateur.php`, `JournalEvenements.php`               | `templates/espace_employe/index.html.twig`, `templates/espace_employe/avis_detail.html.twig`, scripts SQL modération avis | Couvert |
| Traiter les incidents                  | `/espace-employe/incident/{idCovoiturage}`, `/espace-employe/incident/traite`        | `EspaceEmployeController.php`                                             | `PersistanceEmployePostgresql.php`, `SessionUtilisateur.php`                                        | `templates/espace_employe/incident_detail.html.twig`, scripts SQL incidents                                               | Couvert |
| Gérer les employés                     | `/espace-administrateur/creer-employe`                                               | `EspaceAdminController.php`                                               | `PersistanceAdministrationPostgresql.php`, `SessionUtilisateur.php`                                 | `templates/espace_administrateur/creer_employe.html.twig`                                                                 | Couvert |
| Suspendre ou réactiver un compte       | `/espace-administrateur/suspendre-compte`, `/espace-administrateur/reactiver-compte` | `EspaceAdminController.php`                                               | `PersistanceAdministrationPostgresql.php`, `SessionUtilisateur.php`                                 | `templates/espace_administrateur/index.html.twig`                                                                         | Couvert |
| Consulter les crédits                  | `/credits`                                                                           | `CreditsController.php`                                                   | `PersistanceCreditsPostgresql.php`, `SessionUtilisateur.php`                                        | `templates/credits/index.html.twig`                                                                                       | Couvert |
| Réinitialiser un mot de passe          | `/mot_de_passe_oublie`, `/reinitialiser_mot_de_passe/{jeton}`                        | `MotDePasseOublieController.php`, `ReinitialiserMotDePasseController.php` | `PersistanceReinitialisationMotDePassePostgresql.php`, `EnvoiCourriels.php`                         | `templates/mot_de_passe_oublie/index.html.twig`, `templates/reinitialiser_mot_de_passe/index.html.twig`                   | Couvert |
| Utiliser l’API de géocodage            | `/api/geocodage/adresse`                                                             | `ApiGeocodageController.php`                                              | `GeocodageAdresse.php`                                                                              | Appel JavaScript côté interface                                                                                           | Couvert |
| Journaliser des événements applicatifs | Pas de route dédiée                                                                  | Contrôleurs appelants selon action                                        | `JournalEvenements.php`                                                                             | MongoDB Compass, collection `journal_evenements`                                                                          | Couvert |
| Envoyer des courriels automatiques     | Pas de route dédiée                                                                  | `HistoriqueController.php`, contrôleurs appelants selon action            | `EnvoiCourriels.php`                                                                                | Templates `templates/courriels/`, captures MailHog                                                                        | Couvert |

## 4. Preuves de vérification

Les preuves disponibles sont réparties dans plusieurs dossiers du projet.

Les routes Symfony peuvent être vérifiées avec :

`php bin/console debug:router --show-controllers`

Les contrôleurs sont dans :

`src/Controller/`

Les services sont dans :

`src/Service/`

Les vues Twig sont dans :

`templates/`

Les scripts SQL de vérification sont dans :

`docs/sql/`

Les captures SQL sont dans :

`docs/sql/capture_ecran_requetes_sql/`

Les captures MailHog liées aux courriels automatiques sont dans :

`docs/gestion_projet/captures_tests/courriels_automatiques/`

## 5. Limites

Cette matrice montre que les fonctionnalités prévues possèdent un traitement dans le code.

Elle ne signifie pas que tous les parcours sont couverts par des tests automatisés.

Les tests automatisés existants sont documentés dans :

`tests/README.md`

Les contrôles qualité et sécurité sont documentés dans :

`docs/qualite_securite/README.md`
