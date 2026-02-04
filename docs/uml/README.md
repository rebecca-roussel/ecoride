# Diagrammes UML de l’application EcoRide

Ce dossier regroupe l’ensemble des diagrammes UML réalisés pour le projet EcoRide.
Ces diagrammes ont été utilisés comme outils de conception afin de structurer
le fonctionnement de l’application avant l’implémentation technique.

Ils permettent de représenter le système selon différents points de vue :
fonctionnel, dynamique et structurel.

## 1. Diagramme de cas d’utilisation

Le diagramme de cas d’utilisation a été réalisé afin d’obtenir une vision
fonctionnelle globale de l’application, indépendante de toute considération
technique ou d’interface.

Il met en évidence les interactions possibles entre les différents profils
d’utilisateurs et le système, ainsi que les objectifs poursuivis par chacun.

Les profils identifiés sont :

- le visiteur non connecté,
- l’utilisateur inscrit,
- l’employé chargé de la modération,
- l’administrateur.

Les cas d’utilisation sont formulés en langage courant et décrivent uniquement
les actions possibles et leur finalité. La relation `<<include>>` est utilisée
lorsqu’une action est nécessaire à la réalisation d’une autre, notamment pour
l’authentification préalable à certaines fonctionnalités.

La relation `<<extend>>` n’a pas été retenue, les comportements représentés
correspondant ici à des usages obligatoires ou à des droits distincts selon
le profil de l’utilisateur.

Ce diagramme constitue le point d’entrée pour la compréhension fonctionnelle
du projet.

## 2. Diagrammes d’activités

Les diagrammes d’activités ont été construits à partir des parcours utilisateurs
issus du user story mapping, disponible dans le dossier `gestion_projet`.

Ils ont été utilisés comme outils de clarification afin de représenter le
déroulement complet des actions, les décisions possibles et les éventuels
retours en arrière.

Ils jouent un rôle de transition entre l’expression des besoins fonctionnels
et les diagrammes plus détaillés.

Les conventions UML retenues sont volontairement simples :

- un point de départ unique,
- des actions représentées par des rectangles,
- des décisions représentées par des losanges,
- des flux clairement identifiés,
- un point de fin explicite.

## Diagrammes de séquence

Les diagrammes de séquence modélisent les échanges chronologiques entre les acteurs, l’interface utilisateur et le système pour les principaux scénarios fonctionnels de l’application.

Chaque diagramme correspond à un scénario clairement identifié (inscription et connexion, recherche de covoiturage, participation, publication, annulation, modération, gestion des espaces utilisateur, employé et administrateur).

Les variantes de comportement sont représentées à l’aide de fragments d’alternative (alt), tandis que les comportements conditionnels ou optionnels sont modélisés par des fragments optionnels (opt), conformément à la notation UML.

Pour chaque diagramme sont fournis :

- une version lisible au format PDF,

- une image PNG destinée à la consultation rapide.

une description écrite du scénario et des choix de modélisation associés.

## 4. Diagramme de classes

Le diagramme de classes présente la structure statique du projet EcoRide.
Il décrit les principales entités métier, leurs attributs essentiels
et les relations qui les lient.

La classe `Utilisateur` constitue le cœur du modèle et regroupe les informations
communes à toute personne utilisant la plateforme. Les rôles internes
`Employé` et `Administrateur` sont modélisés par héritage afin de mutualiser
les propriétés communes tout en ajoutant des responsabilités spécifiques.

Les classes `Voiture`, `Covoiturage`, `Participation` et `Avis`
structurent les éléments centraux de l’activité.
Les relations et cardinalités traduisent les règles fonctionnelles définies
dans le projet.

Les choix techniques d’implémentation associés à ce modèle sont explicités
dans les espaces dédiés de l’environnement de travail (Notion).

## 5. Vocabulaire fonctionnel

Afin d’assurer la cohérence entre les diagrammes UML, la modélisation des données
et l’implémentation, un vocabulaire fonctionnel cohérent est utilisé dans
l’ensemble des diagrammes.

Les termes canoniques retenus sont notamment :

- **Visiteur** : personne consultant le site sans être authentifiée.
- **Utilisateur** : personne disposant d’un compte et authentifiée.
- **Chauffeur / Passager** : rôles fonctionnels qu’un utilisateur peut assumer.
- **Covoiturage** : offre de déplacement publiée sur la plateforme.
- **Participation** : acte par lequel un utilisateur rejoint un covoiturage.
- **Avis** : retour laissé à l’issue d’une participation.

Ces diagrammes constituent une base de conception et de cohérence pour les
étapes suivantes du projet.
