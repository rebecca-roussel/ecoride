# Captures de tests des courriels automatiques

Ce dossier regroupe les captures utilisées pour vérifier les courriels automatiques générés par EcoRide pendant les tests locaux avec MailHog.

MailHog permet d’intercepter les courriels envoyés par l’application sans utiliser une vraie boîte mail. Ces captures servent donc de preuve pour montrer que certains parcours déclenchent bien un message.

## Parcours vérifiés

Les captures documentent deux parcours principaux :

- l’envoi d’un courriel après la fin d’un covoiturage, pour demander au passager de valider le trajet ;
- l’envoi d’un courriel après l’annulation d’un covoiturage.

## Ordre des captures

1. `01_mailhog_avant_test.png`  
   État initial de MailHog avant le test.

2. `02_benjamin_connecte.png`  
   Connexion de Benjamin pour exécuter le parcours côté chauffeur.

3. `03_historique_avant_test.png`  
   État de l’historique avant l’action testée.

4. `04_apres_demarrage.png`  
   État après le démarrage du covoiturage.

5. `05_apres_terminaison.png`  
   État après la terminaison du covoiturage.

6. `06_mailhog_liste_validation.png`  
   Liste MailHog montrant le courriel lié à la validation du covoiturage.

7. `07_mailhog_contenu_validation.png`  
   Contenu du courriel de validation intercepté dans MailHog.

8. `08_avant_annulation.png`  
   État avant l’annulation du covoiturage.

9. `09_apres_annulation.png`  
   État après l’annulation du covoiturage.

10. `10_mailhog_liste_annulation.png`  
   Liste MailHog montrant le courriel lié à l’annulation.

11. `11_mailhog_contenu_annulation.png`  
   Contenu du courriel d’annulation intercepté dans MailHog.

## Utilisation dans le dossier projet

Ces captures peuvent être utilisées comme preuve dans la partie consacrée aux composants métier côté serveur, notamment pour montrer que les actions liées aux covoiturages déclenchent bien les courriels attendus.

Elles peuvent aussi être citées en annexe pour justifier le fonctionnement de MailHog dans l’environnement local.
