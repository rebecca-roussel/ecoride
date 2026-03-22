# Captures de tests — courriels automatiques

Ce dossier contient des captures d’écran réalisées pendant des tests de fonctionnement liés aux courriels automatiques dans EcoRide.

Il regroupe deux séries de captures :

- une série liée à un scénario de validation ou de fin de covoiturage ;
- une série liée à un scénario d’annulation.

Chaque fichier correspond à une étape précise du test. L’ensemble permet de conserver une trace visuelle du déroulement du scénario, depuis l’état initial jusqu’à l’affichage du courriel dans MailHog.

## Contenu du dossier

### Série 1 — validation / fin de trajet

- `01_mailhog_avant_test.png`
- `02_benjamin_connecte.png`
- `03_historique_avant_test.png`
- `04_apres_demarrage.png`
- `05_apres_terminaison.png`
- `06_mailhog_liste_validation.png`
- `07_mailhog_contenu_validation.png`

### Série 2 — annulation

- `08_avant_annulation.png`
- `09_apres_annulation.png`
- `10_mailhog_liste_annulation.png`
- `11_mailhog_contenu_annulation.png`

## Pourquoi ce dossier existe

Ce dossier sert à garder une preuve visuelle des tests réalisés sur les envois automatiques de courriels.

Il permet de montrer :

- l’état de l’interface avant une action ;
- le changement visible dans l’application après cette action ;
- l’arrivée du courriel correspondant dans MailHog ;
- le contenu du message généré.

Ces captures complètent les autres éléments de test en montrant concrètement ce qui a été observé pendant le scénario
