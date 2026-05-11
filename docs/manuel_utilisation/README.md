# Manuel d'utilisation - EcoRide

Le manuel d’utilisation complet est disponible au format PDF dans le dossier du projet :

`docs/manuel_utilisation_ecoride.pdf`

Il présente les parcours principaux de l’application EcoRide.
Ci-dessous, vous trouverez les informations principales du document PDF.

> **Note importante sur le comportement des données de démonstration :** le fonctionnement métier présenté dans ce manuel est identique sur l'environnement de production `www.eco-ride.fr`. Les parcours (recherche, participation, publication, historique, modération, administration) restent les mêmes, seules les données affichées peuvent différer selon l'environnement.

---

## 1) Démarrer l'application

1. Ouvrez EcoRide dans votre navigateur :
   - en production : `https://www.eco-ride.fr` (ou `www.eco-ride.fr`),
   - en local (développement) : `http://localhost:8080`.
2. Si nécessaire, utilisez les comptes de démonstration indiqués dans `README.md`.
3. Connectez-vous via **Connexion**.

---

## 2) Créer un compte et se connecter

### Inscription

1. Cliquez sur **Inscription**.
2. Renseignez vos informations (identité, email, mot de passe).
3. Validez le formulaire.

### Connexion

1. Cliquez sur **Connexion**.
2. Entrez votre email et votre mot de passe.
3. Après validation, vous êtes redirigé vers :
   - le **Tableau de bord** (utilisateur standard),
   - l'**Espace employé** (compte employé),
   - l'**Espace administrateur** (compte administrateur).

### Mot de passe oublié

1. Depuis la page de connexion, cliquez sur **Mot de passe oublié**.
2. Entrez votre adresse email.
3. Ouvrez l'email reçu (via MailHog en local) et suivez le lien de réinitialisation.
4. Définissez un nouveau mot de passe.

---

## 3) Rechercher un covoiturage (passager)

1. Ouvrez la page **Recherche**.
2. Saisissez :
   - lieu de départ,
   - lieu d'arrivée,
   - date du trajet,
   - préférences éventuelles.
3. Lancez la recherche pour afficher la page **Résultats**.
4. Ouvrez la fiche **Détails** d'un trajet pour consulter :
   - le prix,
   - les places disponibles,
   - les informations chauffeur/véhicule,
   - le caractère écologique du trajet.
5. Cliquez sur **Participer** pour confirmer votre réservation (si vous êtes connecté et avez assez de crédits).

---

## 4) Publier un covoiturage (chauffeur)

1. Connectez-vous avec un compte utilisateur chauffeur.
2. Allez dans **Publier**.
3. Renseignez les informations du trajet :
   - départ / arrivée,
   - date / heure,
   - nombre de places,
   - prix,
   - véhicule.
4. Validez la publication.
5. Le trajet devient visible dans les résultats de recherche.

---

## 5) Gérer ses véhicules

1. Ouvrez **Espace > Véhicules**.
2. Vous pouvez :
   - **Ajouter** un véhicule,
   - **Modifier** un véhicule existant,
   - **Supprimer** un véhicule.
3. Associez un véhicule adapté avant de publier un trajet.

---

## 6) Tableau de bord, profil et crédits

### Tableau de bord

Le **Tableau de bord** centralise les accès rapides vers :

- profil,
- rôles utilisateur,
- gestion des véhicules,
- historique d'activité.

### Profil

Dans **Profil**, vous pouvez mettre à jour vos informations personnelles.

### Crédits

Dans **Crédits**, vous consultez votre solde actuel pour participer aux covoiturages.

---

## 7) Historique et suivi des trajets

La page **Historique** permet de gérer deux volets :

- **Covoiturages** (trajets que vous conduisez)
  - démarrer un trajet,
  - terminer un trajet,
  - annuler un trajet,
  - déclarer un incident.

- **Participations** (trajets en tant que passager)
  - annuler une participation,
  - déposer un avis/satisfaction après le trajet.

---

## 8) Modération employé

Les comptes employés accèdent à **Espace employé** pour :

1. **Modérer les avis**
   - consulter le détail d'un avis,
   - valider l'avis,
   - refuser l'avis.

2. **Traiter les signalements d'incident**
   - consulter le détail d'un incident,
   - marquer un signalement comme traité.

---

## 9) Administration

Les comptes administrateurs accèdent à **Espace administrateur** pour :

1. Consulter les indicateurs globaux.
2. Gérer les comptes utilisateurs.
3. **Créer un compte employé**.
4. **Suspendre** ou **réactiver** des comptes.

---

## 10) Contact et informations légales

- La page **Contact** permet d'envoyer une demande à l'équipe EcoRide.
- La page **Mentions légales** expose les informations réglementaires du service.

---

## 11) Conseils d'utilisation

- Vérifiez votre solde de crédits avant de réserver.
- Préparez vos véhicules en amont si vous êtes chauffeur.
- Utilisez l'historique pour suivre vos actions et déclarer rapidement un incident si nécessaire.
- Pour les tests locaux d'emails (mot de passe oublié, notifications), consultez MailHog : `http://localhost:8025`.
