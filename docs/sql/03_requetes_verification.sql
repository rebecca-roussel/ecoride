--ROLLBACK;

BEGIN;
/*
  03_requetes_verification.sql
  Objectif : vérifier que les contraintes + triggers du schéma EcoRide fonctionnent
  (places, crédits, validations, incident, commission, modération avis)
*/

/*
   A) Vue rapide : volumes / statuts
 */

-- Comptages par table
SELECT 'utilisateur' AS nom_table, COUNT(*) AS nb FROM utilisateur
UNION ALL SELECT 'voiture', COUNT(*) FROM voiture
UNION ALL SELECT 'covoiturage', COUNT(*) FROM covoiturage
UNION ALL SELECT 'participation', COUNT(*) FROM participation
UNION ALL SELECT 'avis', COUNT(*) FROM avis
UNION ALL SELECT 'commission_plateforme', COUNT(*) FROM commission_plateforme
ORDER BY 1;

-- Covoiturages par statut
SELECT statut_covoiturage, COUNT(*) AS nb
FROM covoiturage
GROUP BY statut_covoiturage
ORDER BY statut_covoiturage;

-- Participations par statut_validation
SELECT statut_validation, COUNT(*) AS nb
FROM participation
GROUP BY statut_validation
ORDER BY statut_validation;


/*
   B) Vérifier role_interne (cohérence employé/admin)
 */

-- Admins : doivent être role_interne = true
SELECT util.pseudo, util.role_interne
FROM administrateur admi
JOIN utilisateur util ON util.id_utilisateur = admi.id_utilisateur;

-- Employés : doivent être role_interne = true (même si suspendus)
SELECT util.pseudo, util.statut, util.role_interne
FROM employe empl
JOIN utilisateur util ON util.id_utilisateur = empl.id_utilisateur
ORDER BY util.pseudo;


/*
   C) Vérifier les covoiturages "clés" (A, B, F, G) et leurs places
*/

-- A : 2027-01-01 08:00 (2 participations NON annulées) => nb_places_dispo attendu : 0
SELECT id_covoiturage, date_heure_depart, statut_covoiturage, nb_places_dispo, prix_credits, commission_credits, ville_depart, ville_arrivee
FROM covoiturage
WHERE date_heure_depart = '2027-01-01 08:00:00';

-- B : 2027-01-01 18:30 (participation annulée) => nb_places_dispo attendu : 1 (inchangé)
SELECT id_covoiturage, date_heure_depart, statut_covoiturage, nb_places_dispo, prix_credits, commission_credits
FROM covoiturage
WHERE date_heure_depart = '2027-01-01 18:30:00';

-- F : 2027-01-06 17:30 (2 participations NON annulées) => nb_places_dispo attendu : 0
SELECT id_covoiturage, date_heure_depart, statut_covoiturage, nb_places_dispo, prix_credits, commission_credits
FROM covoiturage
WHERE date_heure_depart = '2027-01-06 17:30:00';

-- G : 2027-01-07 07:40 (passé à TERMINE puis INCIDENT via validation PROBLEME)
SELECT id_covoiturage, date_heure_depart, statut_covoiturage, nb_places_dispo,
       incident_commentaire, incident_resolu
FROM covoiturage
WHERE date_heure_depart = '2027-01-07 07:40:00';


/*
   D) Vérifier crédits + annulation (débit/remboursement)
 */

-- tous commencent à 20 crédits
-- Raoul a réservé A (12) + F (11) + G (12) => 20 - 12 - 11 - 12 = -15 (impossible)
-- Donc, IMPORTANT : avec le trigger "crédits insuffisants", cette démo suppose
-- que je laisses les crédits à 20 uniquement si je n'interdis pas ce cumul.
-- Si on interdis, il faut augmenter le crédit initial (ex: 50) ou baisser les prix.

-- Vérification crédits des passagers "actifs" après insert + scénario validation
SELECT pseudo, credits
FROM utilisateur
WHERE pseudo IN ('raoul','nina','luc','muriel','benjamin')
ORDER BY pseudo;

-- Détail des participations (qui a payé quoi / annulé quoi)
SELECT util.pseudo, covo.date_heure_depart, part.credits_utilises, part.est_annulee, part.statut_validation
FROM participation part
JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
ORDER BY covo.date_heure_depart, util.pseudo;

-- Focus annulation : Nina sur B (doit être est_annulee=true)
SELECT util.pseudo, covo.date_heure_depart, part.credits_utilises, part.est_annulee
FROM participation part
JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
WHERE util.pseudo='nina' AND covo.date_heure_depart='2027-01-01 18:30:00';


/*
   E) Vérifier le trigger "TERMINE => EN_ATTENTE" sur G
 */

-- Après UPDATE covoiturage(G) -> TERMINE, les participations non annulées doivent être EN_ATTENTE
-- MAIS ensuite on a validé OK pour Raoul et PROBLEME pour Nina.
-- Donc, attendu final :
-- - Raoul sur G : OK
-- - Nina sur G : PROBLEME (+ commentaire_validation non vide)
SELECT util.pseudo, part.statut_validation, part.commentaire_validation
FROM participation part
JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
WHERE covo.date_heure_depart = '2027-01-07 07:40:00'
ORDER BY util.pseudo;


/*
   F) Vérifier commission_plateforme (créée uniquement sur validation OK)
    */

-- La validation OK de Raoul sur G doit avoir inséré 1 commission (credits_commission=2)
SELECT comm.id_commission, comm.date_commission, comm.credits_commission,
       util.pseudo AS pseudo_passager, covo.date_heure_depart
FROM commission_plateforme comm
JOIN participation part ON part.id_participation = comm.id_participation
JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
ORDER BY comm.id_commission;

-- Vérifier la contrainte d'unicité : 1 commission max par participation
SELECT id_participation, COUNT(*) AS nb
FROM commission_plateforme
GROUP BY id_participation
HAVING COUNT(*) > 1;


/*
   G) Vérifier crédit chauffeur suite validation OK (gain = prix - commission)
 */

-- Pour G : prix=12, commission=2 => gain chauffeur = 10
-- Chauffeur de G = Muriel
SELECT util.pseudo, util.credits
FROM utilisateur util
WHERE util.pseudo = 'muriel';

-- Confirmation chauffeur du trajet G
SELECT util.pseudo AS chauffeur, covo.date_heure_depart, covo.prix_credits, covo.commission_credits
FROM covoiturage covo
JOIN utilisateur util ON util.id_utilisateur = covo.id_utilisateur
WHERE covo.date_heure_depart = '2027-01-07 07:40:00';


/*
   H) Avis : 1 par participation + modération + FK employé
*/

-- Liste des avis avec statut + modérateur (si présent)
SELECT avis.id_avis,
       util.pseudo AS auteur,
       covo.date_heure_depart,
       avis.note,
       avis.statut_moderation,
       mode_util.pseudo AS pseudo_moderateur,
       mode_util.statut AS statut_moderateur
FROM avis avis
JOIN participation part ON part.id_participation = avis.id_participation
JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
LEFT JOIN employe mode_empl ON mode_empl.id_utilisateur = avis.id_employe_moderateur
LEFT JOIN utilisateur mode_util ON mode_util.id_utilisateur = mode_empl.id_utilisateur
ORDER BY covo.date_heure_depart, util.pseudo;

COMMIT;
