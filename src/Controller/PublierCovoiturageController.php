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

/**
 * Contrôleur de publication d'un covoiturage.
 *
 * Cette classe gère le formulaire qui permet à un utilisateur connecté
 * de publier un nouveau trajet.
 *
 * Le contrôleur garde ici le rôle lié au web :
 * contrôler l'accès à la page,
 * lire les données envoyées par le formulaire,
 * vérifier les champs,
 * afficher les messages d'erreur,
 * puis rediriger après succès.
 *
 * L'écriture dans PostgreSQL est confiée à `PersistanceCovoituragePostgresql`.
 * La lecture des voitures disponibles est confiée à `PersistanceVoiturePostgresql`.
 * La journalisation MongoDB intervient seulement quand le covoiturage
 * a réellement été créé.
 */
final class PublierCovoiturageController extends AbstractController
{
    /**
     * Affiche le formulaire de publication et traite son envoi.
     *
     * En GET, la méthode affiche le formulaire avec la liste
     * des voitures actives de l'utilisateur.
     *
     * En POST, elle lit les champs, applique les vérifications nécessaires,
     * construit la date et l'heure de départ ainsi que l'heure d'arrivée,
     * crée le covoiturage dans PostgreSQL,
     * puis enregistre un événement significatif dans MongoDB.
     *
     * @param Request $requete Requête HTTP courante.
     * @param SessionUtilisateur $sessionUtilisateur
     *        Service qui permet de lire l'utilisateur connecté.
     * @param PersistanceVoiturePostgresql $persistanceVoiture
     *        Service de lecture PostgreSQL pour les voitures.
     * @param PersistanceCovoituragePostgresql $persistanceCovoiturage
     *        Service d'écriture PostgreSQL pour les covoiturages.
     * @param JournalEvenements $journalEvenements
     *        Service de journalisation MongoDB.
     *
     * @return Response Réponse HTML rendue par Twig ou redirection.
     */
    #[Route('/publier', name: 'publier_covoiturage', methods: ['GET', 'POST'])]
    public function index(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceVoiturePostgresql $persistanceVoiture,
        PersistanceCovoituragePostgresql $persistanceCovoiturage,
        JournalEvenements $journalEvenements
    ): Response {
        /*
         * La publication d'un trajet n'est possible
         * que pour un utilisateur connecté.
         */
        $utilisateur = $sessionUtilisateur->obtenirUtilisateurConnecte();
        if ($utilisateur === null) {
            $this->addFlash('erreur', 'Veuillez vous connecter pour accéder à cette page.');

            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        /*
         * Le rôle chauffeur est nécessaire pour publier un covoiturage.
         * Si ce rôle n'est pas actif, l'utilisateur est renvoyé
         * vers son tableau de bord avec un message explicite.
         */
        $estChauffeur = (bool) ($utilisateur['role_chauffeur'] ?? false);
        if (!$estChauffeur) {
            $this->addFlash('erreur', 'Vous devez activer le rôle chauffeur pour publier un covoiturage.');

            return $this->redirectToRoute('tableau_de_bord');
        }

        /*
         * Le formulaire propose uniquement les voitures actives
         * appartenant à l'utilisateur connecté.
         */
        $voitures = $persistanceVoiture->listerVehiculesActifsParUtilisateur($idUtilisateur);

        if ($requete->isMethod('GET')) {
            return $this->render('publier/index.html.twig', [
                'voitures' => $voitures,
                'valeurs' => [],
                'erreurs' => [],
            ]);
        }

        /*
         * Le jeton CSRF protège l'envoi du formulaire.
         * Il sert à vérifier que la requête vient bien de l'application.
         */
        $jeton = (string) $requete->request->get('_token', '');
        if (!$this->isCsrfTokenValid('publier_covoiturage', $jeton)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        /*
         * Lecture des champs texte et numériques envoyés par le formulaire.
         * `trim()` retire les espaces inutiles autour des valeurs texte.
         */
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

        /*
         * Les coordonnées géographiques peuvent être absentes.
         * Si le champ est vide, on garde `null`.
         * Sinon, on convertit la valeur en nombre décimal.
         */
        $latitudeDepartBrut = trim((string) $requete->request->get('latitude_depart', ''));
        $longitudeDepartBrut = trim((string) $requete->request->get('longitude_depart', ''));
        $latitudeArriveeBrut = trim((string) $requete->request->get('latitude_arrivee', ''));
        $longitudeArriveeBrut = trim((string) $requete->request->get('longitude_arrivee', ''));

        $latitudeDepart = ($latitudeDepartBrut === '') ? null : (float) $latitudeDepartBrut;
        $longitudeDepart = ($longitudeDepartBrut === '') ? null : (float) $longitudeDepartBrut;
        $latitudeArrivee = ($latitudeArriveeBrut === '') ? null : (float) $latitudeArriveeBrut;
        $longitudeArrivee = ($longitudeArriveeBrut === '') ? null : (float) $longitudeArriveeBrut;

        /*
         * `has()` permet de savoir si une case a été cochée.
         * Une case décochée n'est pas envoyée dans la requête.
         */
        $estNonFumeur = $requete->request->has('est_non_fumeur');
        $accepteAnimaux = $requete->request->has('accepte_animaux');

        $preferencesLibre = trim((string) $requete->request->get('preferences_libre', ''));
        if ($preferencesLibre === '') {
            $preferencesLibre = null;
        }

        /*
         * Le tableau `erreurs` stocke les messages associés aux champs.
         * Cela permet de réafficher le formulaire avec des indications précises.
         */
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

        /*
         * Le prix doit au moins couvrir la commission prévue dans le projet.
         */
        if ($prixCredits < 2) {
            $erreurs['prix_credits'] = 'Le prix minimum est de 2 crédits (commission EcoRide incluse).';
        }

        /*
         * Le nombre de places proposé doit rester cohérent
         * avec la capacité de la voiture choisie.
         */
        if ($idVoiture > 0 && !isset($erreurs['nb_places_dispo'])) {
            foreach ($voitures as $voiture) {
                if ((int) ($voiture['id_voiture'] ?? 0) === $idVoiture) {
                    $placesVoiture = (int) ($voiture['nb_places'] ?? 0);

                    if ($placesVoiture > 0 && $nbPlacesDispo > $placesVoiture) {
                        $erreurs['nb_places_dispo'] = 'Les places disponibles ne peuvent pas dépasser les places de la voiture.';
                    }

                    break;
                }
            }
        }

        /*
         * Une coordonnée géographique doit toujours venir par paire :
         * latitude + longitude.
         * Si l'une existe sans l'autre, l'adresse est considérée incomplète.
         */
        $departIncomplet = ($latitudeDepart === null) !== ($longitudeDepart === null);
        if ($departIncomplet) {
            $erreurs['adresse_depart'] = 'Coordonnées de départ incomplètes. Veuillez retaper l’adresse.';
        }

        $arriveeIncomplet = ($latitudeArrivee === null) !== ($longitudeArrivee === null);
        if ($arriveeIncomplet) {
            $erreurs['adresse_arrivee'] = 'Coordonnées d’arrivée incomplètes. Veuillez retaper l’adresse.';
        }

        /*
         * Une latitude doit rester entre -90 et 90.
         * Une longitude doit rester entre -180 et 180.
         */
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

        /*
         * La date et l'heure de départ sont d'abord combinées dans un objet date.
         * Un objet `DateTimeImmutable` représente une date et une heure complètes.
         * Le mot "Immutable" signifie que l'objet n'est pas modifié directement :
         * une nouvelle valeur est produite à chaque calcul.
         */
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

        /*
         * Si des erreurs existent, le formulaire est réaffiché
         * avec les valeurs déjà saisies.
         * Cela évite à l'utilisateur de tout retaper.
         */
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

        /*
         * L'écriture du covoiturage dans PostgreSQL est centralisée
         * dans le service de persistance.
         */
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

        /*
         * La journalisation intervient après succès.
         * Elle conserve ici une trace utile de la publication du trajet.
         */
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