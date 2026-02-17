<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceCovoituragePostgresql;
use App\Service\PersistanceVoiturePostgresql;
use App\Service\SessionUtilisateur;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublierCovoiturageController extends AbstractController
{
    /*
      PLAN (PublierCovoiturageController) :

      1) Sécurité
         - page réservée aux utilisateurs connectés 
         - publication réservée aux chauffeurs

      2) Afficher le formulaire (GET)
         - lister les véhicules actifs de l’utilisateur
         - journaliser l’ouverture
         - afficher publier/index.html.twig

      3) Traiter la publication (POST)
         - CSRF
         - lire les champs
         - valider (par champ, pour affichage sous chaque champ)
         - construire date_heure_depart + date_heure_arrivee
         - créer le covoiturage en base
         - journaliser
         - rediriger vers la page détails
    */

    #[Route('/publier', name: 'publier_covoiturage', methods: ['GET', 'POST'])]
    public function index(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceVoiturePostgresql $persistanceVoiture,
        PersistanceCovoituragePostgresql $persistanceCovoiturage,
        JournalEvenements $journalEvenements
    ): Response {
        $utilisateur = $sessionUtilisateur->obtenirUtilisateurConnecte();
        if ($utilisateur === null) {
            $this->addFlash('erreur', 'Veuillez vous connecter pour accéder à cette page.');
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        $estChauffeur = (bool) ($utilisateur['role_chauffeur'] ?? false);
        if (!$estChauffeur) {
            $this->addFlash('erreur', 'Vous devez activer le rôle chauffeur pour publier un covoiturage.');
            return $this->redirectToRoute('tableau_de_bord');
        }

        $voitures = $persistanceVoiture->listerVehiculesActifsParUtilisateur($idUtilisateur);

        if ($requete->isMethod('GET')) {
            $journalEvenements->enregistrer(
                'page_ouverte',
                'publier_covoiturage',
                $idUtilisateur,
                ['nb_voitures_actives' => count($voitures)]
            );

            return $this->render('publier/index.html.twig', [
                'voitures' => $voitures,
                'valeurs' => [],
                'erreurs' => [],
            ]);
        }

        $jeton = (string) $requete->request->get('_token', '');
        if (!$this->isCsrfTokenValid('publier_covoiturage', $jeton)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $villeDepart = trim((string) $requete->request->get('ville_depart', ''));
        $villeArrivee = trim((string) $requete->request->get('ville_arrivee', ''));
        $adresseDepart = trim((string) $requete->request->get('adresse_depart', ''));
        $adresseArrivee = trim((string) $requete->request->get('adresse_arrivee', ''));

        $dateDepart = trim((string) $requete->request->get('date_depart', ''));
        $heureDepart = trim((string) $requete->request->get('heure_depart', ''));
        $dureeMinutes = (int) $requete->request->get('duree_minutes', 0);

        $nbPlacesDispo = (int) $requete->request->get('nb_places_dispo', 0);
        $prixCredits = (int) $requete->request->get('prix_credits', 0);
        $idVoiture = (int) $requete->request->get('id_voiture', 0);

        $latitudeDepartBrut = trim((string) $requete->request->get('latitude_depart', ''));
        $longitudeDepartBrut = trim((string) $requete->request->get('longitude_depart', ''));
        $latitudeArriveeBrut = trim((string) $requete->request->get('latitude_arrivee', ''));
        $longitudeArriveeBrut = trim((string) $requete->request->get('longitude_arrivee', ''));

        $latitudeDepart = ($latitudeDepartBrut === '') ? null : (float) $latitudeDepartBrut;
        $longitudeDepart = ($longitudeDepartBrut === '') ? null : (float) $longitudeDepartBrut;
        $latitudeArrivee = ($latitudeArriveeBrut === '') ? null : (float) $latitudeArriveeBrut;
        $longitudeArrivee = ($longitudeArriveeBrut === '') ? null : (float) $longitudeArriveeBrut;

        $estNonFumeur = $requete->request->has('est_non_fumeur');
        $accepteAnimaux = $requete->request->has('accepte_animaux');

        $preferencesLibre = trim((string) $requete->request->get('preferences_libre', ''));
        if ($preferencesLibre === '') {
            $preferencesLibre = null;
        }

        $erreurs = [];

        if ($villeDepart === '') {
            $erreurs['ville_depart'] = 'La ville de départ est obligatoire.';
        }
        if ($villeArrivee === '') {
            $erreurs['ville_arrivee'] = 'La ville d’arrivée est obligatoire.';
        }
        if ($adresseDepart === '') {
            $erreurs['adresse_depart'] = 'L’adresse de départ est obligatoire.';
        }
        if ($adresseArrivee === '') {
            $erreurs['adresse_arrivee'] = 'L’adresse d’arrivée est obligatoire.';
        }

        if ($dateDepart === '') {
            $erreurs['date_depart'] = 'La date est obligatoire.';
        }
        if ($heureDepart === '') {
            $erreurs['heure_depart'] = 'L’heure est obligatoire.';
        }
        if ($dureeMinutes < 10 || $dureeMinutes > 1440) {
            $erreurs['duree_minutes'] = 'La durée doit être comprise entre 10 et 1440 minutes.';
        }

        if ($nbPlacesDispo < 1 || $nbPlacesDispo > 4) {
            $erreurs['nb_places_dispo'] = 'Le nombre de places doit être compris entre 1 et 4.';
        }
        if ($idVoiture <= 0) {
            $erreurs['id_voiture'] = 'Vous devez sélectionner une voiture.';
        }

        if ($prixCredits < 2) {
            $erreurs['prix_credits'] = 'Le prix minimum est de 2 crédits (commission EcoRide incluse).';
        }

        if ($idVoiture > 0 && !isset($erreurs['nb_places_dispo'])) {
            foreach ($voitures as $v) {
                if ((int) ($v['id_voiture'] ?? 0) === $idVoiture) {
                    $placesVoiture = (int) ($v['nb_places'] ?? 0);

                    if ($placesVoiture > 0 && $nbPlacesDispo > $placesVoiture) {
                        $erreurs['nb_places_dispo'] = 'Les places disponibles ne peuvent pas dépasser les places de la voiture.';
                    }

                    break;
                }
            }
        }

        $departIncomplet = ($latitudeDepart === null) !== ($longitudeDepart === null);
        if ($departIncomplet) {
            $erreurs['adresse_depart'] = 'Coordonnées de départ incomplètes. Veuillez retaper l’adresse.';
        }

        $arriveeIncomplet = ($latitudeArrivee === null) !== ($longitudeArrivee === null);
        if ($arriveeIncomplet) {
            $erreurs['adresse_arrivee'] = 'Coordonnées d’arrivée incomplètes. Veuillez retaper l’adresse.';
        }

        if ($latitudeDepart !== null && ($latitudeDepart < -90 || $latitudeDepart > 90)) {
            $erreurs['adresse_depart'] = 'Latitude de départ invalide.';
        }
        if ($longitudeDepart !== null && ($longitudeDepart < -180 || $longitudeDepart > 180)) {
            $erreurs['adresse_depart'] = 'Longitude de départ invalide.';
        }
        if ($latitudeArrivee !== null && ($latitudeArrivee < -90 || $latitudeArrivee > 90)) {
            $erreurs['adresse_arrivee'] = 'Latitude d’arrivée invalide.';
        }
        if ($longitudeArrivee !== null && ($longitudeArrivee < -180 || $longitudeArrivee > 180)) {
            $erreurs['adresse_arrivee'] = 'Longitude d’arrivée invalide.';
        }

        $dateHeureDepart = null;
        $dateHeureArrivee = null;

        if (!isset($erreurs['date_depart']) && !isset($erreurs['heure_depart']) && !isset($erreurs['duree_minutes'])) {
            $dateHeureDepart = DateTimeImmutable::createFromFormat('Y-m-d H:i', $dateDepart . ' ' . $heureDepart);

            if (!$dateHeureDepart) {
                $erreurs['date_depart'] = 'Date/heure invalides.';
            } else {
                $dateHeureArrivee = $dateHeureDepart->modify('+' . $dureeMinutes . ' minutes');

                if (!$dateHeureArrivee) {
                    $erreurs['duree_minutes'] = 'Impossible de calculer l’heure d’arrivée.';
                }
            }
        }

        if (!empty($erreurs)) {
            $this->addFlash('erreur', 'Certains champs contiennent des erreurs. Vérifiez le formulaire.');

            return $this->render('publier/index.html.twig', [
                'voitures' => $voitures,
                'valeurs' => [
                    'ville_depart' => $villeDepart,
                    'ville_arrivee' => $villeArrivee,
                    'adresse_depart' => $adresseDepart,
                    'adresse_arrivee' => $adresseArrivee,
                    'date_depart' => $dateDepart,
                    'heure_depart' => $heureDepart,
                    'duree_minutes' => $dureeMinutes,
                    'nb_places_dispo' => $nbPlacesDispo,
                    'prix_credits' => $prixCredits,
                    'id_voiture' => $idVoiture,
                    'est_non_fumeur' => $estNonFumeur,
                    'accepte_animaux' => $accepteAnimaux,
                    'preferences_libre' => $preferencesLibre,
                    'latitude_depart' => $latitudeDepart,
                    'longitude_depart' => $longitudeDepart,
                    'latitude_arrivee' => $latitudeArrivee,
                    'longitude_arrivee' => $longitudeArrivee,
                ],
                'erreurs' => $erreurs,
            ]);
        }

        \assert($dateHeureDepart instanceof DateTimeImmutable);
        \assert($dateHeureArrivee instanceof DateTimeImmutable);

        $idCovoiturage = $persistanceCovoiturage->creerCovoituragePlanifie(
            $idUtilisateur,
            $idVoiture,
            $dateHeureDepart,
            $dateHeureArrivee,
            $adresseDepart,
            $adresseArrivee,
            $villeDepart,
            $villeArrivee,
            $latitudeDepart,
            $longitudeDepart,
            $latitudeArrivee,
            $longitudeArrivee,
            $nbPlacesDispo,
            $prixCredits,
            $estNonFumeur,
            $accepteAnimaux,
            $preferencesLibre
        );

        $journalEvenements->enregistrer(
            'covoiturage_publie',
            'covoiturage',
            (int) $idCovoiturage,
            [
                'id_chauffeur' => $idUtilisateur,
                'ville_depart' => $villeDepart,
                'ville_arrivee' => $villeArrivee,
                'prix_credits' => $prixCredits,
            ]
        );

        $this->addFlash('succes', 'Covoiturage publié.');

        return $this->redirectToRoute('details', ['id' => $idCovoiturage]);
    }
}
