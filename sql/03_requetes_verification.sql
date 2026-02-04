SELECT id_utilisateur, pseudo, statut
FROM utilisateur
ORDER BY id_utilisateur;
-- vérification des rôles spécialisés depuis utilisateur
SELECT id_utilisateur
FROM administrateur;
SELECT id_utilisateur
FROM employe;
-- vérification que les chauffeurs possède une voiure 
SELECT
  util.id_utilisateur,
  util.pseudo,
  voit.id_voiture,
  voit.immatriculation,
  voit.energie
FROM voiture AS voit
JOIN utilisateur AS util
  ON util.id_utilisateur = voit.id_utilisateur
ORDER BY voit.id_voiture;
-- vérification que le passager à une participation
SELECT
  part.id_participation,
  util.id_utilisateur,
  util.pseudo,
  covo.id_covoiturage,
  covo.ville_depart,
  covo.ville_arrivee,
  covo.date_heure_depart,
  part.credits_utilises,
  part.est_annulee,
  part.date_heure_confirmation
FROM participation AS part
JOIN utilisateur AS util
  ON util.id_utilisateur = part.id_utilisateur
JOIN covoiturage AS covo
  ON covo.id_covoiturage = part.id_covoiturage
WHERE util.pseudo = 'raoul'
ORDER BY part.id_participation;
-- vérification que l'avis passager est modéré par l'employé
SELECT
  avis.id_avis,
  avis.note,
  avis.commentaire,
  avis.date_depot,
  avis.statut_moderation,
  util.pseudo AS auteur_avis,
  util_mod.pseudo AS moderateur_avis
FROM avis AS avis
JOIN participation AS part
  ON part.id_participation = avis.id_participation
JOIN utilisateur AS util
  ON util.id_utilisateur = part.id_utilisateur
JOIN utilisateur AS util_mod
  ON util_mod.id_utilisateur = avis.id_employe_moderateur
WHERE util.pseudo = 'raoul'
  AND util_mod.pseudo = 'sophie'
ORDER BY avis.id_avis;