# Cahier des charges — EcoRide

## Contexte

EcoRide est un projet d’application web porté par une startup fictive
souhaitant encourager le covoiturage comme alternative aux déplacements
individuels, dans une démarche de réduction de l’impact environnemental.

Le projet est piloté par José, directeur technique, qui souhaite disposer
d’une application dédiée exclusivement aux déplacements en voiture.
L’objectif est de proposer une plateforme simple et fonctionnelle permettant
de mettre en relation des conducteurs et des passagers pour des trajets partagés.

EcoRide s’adresse à des utilisateurs soucieux de l’environnement, ainsi qu’à
des personnes recherchant une solution de mobilité économique et pratique.

Dans le cadre de cette mission, il est demandé de concevoir et développer
une application web répondant aux besoins fonctionnels décrits dans le
présent cahier des charges.

---

## Page d’accueil

La page d’accueil constitue le point d’entrée principal de l’application.
Elle présente l’application EcoRide à travers un contenu informatif permettant
au visiteur de comprendre l’objectif général de la plateforme.

Une barre de recherche est directement accessible depuis cette page afin de
permettre aux visiteurs de rechercher un covoiturage en renseignant une ville
de départ, une ville d’arrivée et une date. Cette recherche constitue le
premier point d’interaction fonctionnelle avec l’application.

La page d’accueil comporte également un pied de page affichant l’adresse
e-mail de l’entreprise ainsi qu’un lien vers les mentions légales.

---

## Menu de navigation

Utilisateurs concernés : Visiteurs, Utilisateurs

Le menu de navigation est présent sur l’ensemble de l’application.
Il permet d’accéder aux fonctionnalités principales, notamment :

- la page d’accueil,
- la recherche de covoiturages,
- la connexion à un compte,
- une page de contact.

Le contenu du menu s’adapte au statut de l’utilisateur
(connecté ou non connecté). Les liens proposés sont ajustés en fonction
des fonctionnalités accessibles, tout en conservant une structure
identique afin de garantir une navigation cohérente.

---

## Connexion

Utilisateurs concernés : Utilisateurs, Employés, Administrateurs

Tous les utilisateurs disposant d’un compte doivent pouvoir se connecter
via un formulaire unique. Les identifiants requis sont une adresse e-mail
et un mot de passe.

Les comptes employés et administrateurs ne peuvent pas être créés depuis
l’application. Ils sont créés en amont et enregistrés directement dans
la base de données.

Après authentification, chaque utilisateur accède à un espace adapté
à son rôle et à ses droits.

---

## Création de compte

Utilisateurs concernés : Visiteurs

Un visiteur peut créer un compte utilisateur afin d’accéder aux
fonctionnalités de participation aux covoiturages et à la gestion
de son espace personnel.

Lors de l’inscription, le visiteur doit fournir un pseudo, une adresse
e-mail et un mot de passe.

À la création du compte, l’utilisateur bénéficie automatiquement de
vingt crédits lui permettant de participer aux covoiturages proposés
sur la plateforme.

---

## Vue des covoiturages

Utilisateurs concernés : Visiteurs

Une vue dédiée permet aux visiteurs d’afficher les covoiturages disponibles
sur la plateforme. Par défaut, aucun covoiturage n’est affiché tant qu’une
recherche n’a pas été effectuée.

Le visiteur renseigne une ville de départ, une ville d’arrivée et une date.
À la suite de cette saisie, les covoiturages disponibles sont affichés sous
forme de liste.

Chaque covoiturage présente les informations suivantes :

- le pseudo du chauffeur,
- sa photo et sa note,
- le nombre de places restantes,
- le prix,
- la date ainsi que les horaires de départ et d’arrivée,
- une indication précisant si le covoiturage est considéré comme écologique.

Seuls les covoiturages disposant d’au moins une place disponible sont proposés.
Si aucun covoiturage n’est disponible à la date choisie, il est suggéré au
visiteur de modifier sa recherche vers la date la plus proche proposant un
covoiturage.

Un covoiturage est considéré comme écologique lorsqu’il est effectué à l’aide
d’un véhicule électrique.

---

## Filtres des covoiturages

Utilisateurs concernés : Visiteurs

Après l’affichage des résultats de recherche, le visiteur peut affiner les
covoiturages proposés à l’aide de filtres.

Il est possible de filtrer les covoiturages selon les critères suivants :

- le caractère écologique du covoiturage,
- le prix maximum,
- la durée maximale,
- la note minimale du chauffeur.

---

## Vue détaillée d’un covoiturage

Utilisateurs concernés : Visiteurs, Utilisateurs

En cliquant sur le bouton de détail d’un covoiturage, le visiteur ou
l’utilisateur accède à une page présentant l’ensemble des informations
du covoiturage sélectionné.

Cette page reprend les éléments visibles dans la vue précédente et y ajoute :

- les avis laissés sur le chauffeur,
- les informations relatives au véhicule utilisé, incluant la marque,
  le modèle et l’énergie,
- les préférences du conducteur.

---

## Participer à un covoiturage

Utilisateurs concernés : Visiteurs, Utilisateurs

Depuis la vue détaillée d’un covoiturage, il est possible de rejoindre
un covoiturage à condition qu’il reste des places disponibles et que
l’utilisateur dispose d’un nombre suffisant de crédits.

Si le visiteur n’est pas connecté au moment de l’action, il lui est proposé
de se connecter ou de créer un compte.

Lorsqu’un utilisateur connecté souhaite participer, une double confirmation
est demandée afin de valider l’utilisation des crédits nécessaires.

Une fois la participation confirmée, le nombre de crédits de l’utilisateur
est mis à jour, la participation est enregistrée dans son espace utilisateur
et le nombre de places disponibles du covoiturage est automatiquement ajusté.

---

## Espace utilisateur

Utilisateurs concernés : Utilisateurs

Chaque utilisateur dispose d’un espace personnel lui permettant de consulter
et de modifier ses informations, ainsi que de gérer les rôles qu’il souhaite
assumer sur la plateforme. Il peut choisir d’être chauffeur, passager ou les deux.

Lorsqu’un utilisateur active le rôle de chauffeur, il doit renseigner les
informations relatives à ses véhicules, notamment la plaque d’immatriculation,
la date de première immatriculation, la marque, le modèle, la couleur et le
nombre de places disponibles. Un utilisateur peut enregistrer plusieurs véhicules.

L’utilisateur peut également définir ses préférences, telles que l’acceptation
ou non des fumeurs et des animaux, et ajouter des préférences libres destinées
à informer les autres participants.

Lorsqu’un utilisateur n’assume que le rôle de passager, aucune information
complémentaire n’est requise.

---

## Saisir un covoiturage

Utilisateurs concernés : Utilisateurs

Un utilisateur disposant du rôle de chauffeur peut proposer un nouveau
covoiturage depuis son espace personnel. Il doit renseigner une adresse
de départ, une adresse d’arrivée, une date ainsi que les horaires
de départ et d’arrivée.

Le prix du covoiturage est défini par le chauffeur. Pour chaque
participation enregistrée, deux crédits sont automatiquement prélevés
par la plateforme.

Lors de la création du covoiturage, le chauffeur doit sélectionner
un véhicule parmi ceux déjà enregistrés ou en ajouter un nouveau.

---

## Historique des covoiturages

Utilisateurs concernés : Utilisateurs

Chaque utilisateur peut consulter l’historique de ses covoiturages,
qu’il s’agisse de covoiturages proposés en tant que chauffeur ou
rejoints en tant que passager.

Un utilisateur peut annuler sa participation à un covoiturage.
Dans ce cas, les crédits et le nombre de places disponibles sont
mis à jour.

Lorsqu’un chauffeur annule un covoiturage qu’il a proposé, l’ensemble
des participants est informé par e-mail et les crédits ainsi que les
places disponibles sont ajustés en conséquence.

---

## Démarrer et arrêter un covoiturage

Utilisateurs concernés : Utilisateurs

Un utilisateur disposant du rôle de chauffeur peut démarrer un covoiturage
depuis son espace personnel en cliquant sur le bouton correspondant.
À l’arrivée à destination, il clôture le covoiturage via un bouton dédié.

À la clôture du covoiturage, les participants reçoivent une notification
leur demandant de confirmer que le covoiturage s’est bien déroulé.

Lorsque l’ensemble des participants confirme le bon déroulement du
covoiturage, les crédits du chauffeur sont mis à jour. Les participants
peuvent également laisser un avis et une note concernant le chauffeur.

En cas de problème signalé par un participant, celui-ci peut ajouter
un commentaire. Un employé intervient alors afin de traiter la situation
avant la validation définitive des crédits du chauffeur.

---

## Espace employé

Utilisateurs concernés : Employés

Les employés disposent d’un espace dédié leur permettant de modérer les avis
déposés par les participants avant leur publication. Ils peuvent valider ou
refuser un avis en fonction de son contenu.

Ils ont également accès aux covoiturages signalés comme problématiques.
Cet accès est limité aux informations nécessaires au traitement du signalement,
telles que l’identifiant du covoiturage, les utilisateurs concernés et
les éléments descriptifs du covoiturage.

L’espace employé est exclusivement dédié aux actions de modération.
Aucune action de gestion des comptes ou de modification des covoiturages
n’est possible depuis cet espace.

---

## Espace administrateur

Utilisateurs concernés : Administrateurs

Les administrateurs disposent d’un espace dédié à la gestion globale de la
plateforme EcoRide.

Ils peuvent gérer les comptes utilisateurs et employés, notamment en consultant
les comptes existants et en suspendant un compte si nécessaire.

L’administrateur a également accès à des statistiques globales permettant
d’avoir une vue d’ensemble de l’activité de la plateforme. Ces statistiques
incluent notamment le nombre de covoiturages publiés par jour ainsi que
le nombre de crédits générés par la plateforme au fil du temps.

Le nombre total de crédits gagnés par la plateforme doit être visible
depuis cet espace.

L’espace administrateur est exclusivement dédié à des actions de gestion
et de consultation. Il n’intervient pas dans la modération des avis ou
le traitement des signalements, ces actions relevant du rôle employé.

## Définitions

- Visiteur : utilisateur non connecté.
- Utilisateur : utilisateur authentifié (compte standard).
- Employé / Administrateur : utilisateur authentifié disposant d’un rôle interne.
