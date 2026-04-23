<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceCovoituragePostgresql;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page de résultats.
 *
 * Cette classe gère l'affichage des covoiturages trouvés
 * à partir des critères saisis dans le formulaire de recherche.
 *
 * Son rôle reste centré sur le flux HTTP :
 * lire les paramètres envoyés dans l'URL,
 * nettoyer les valeurs,
 * contrôler les formats attendus,
 * préparer une éventuelle plage horaire,
 * appeler la couche de persistance,
 * puis transmettre les résultats à Twig.
 *
 * La lecture en base de données est déléguée
 * au service PersistanceCovoituragePostgresql.
 */
final class ResultatsController extends AbstractController
{
    /**
     * Affiche les résultats d'une recherche de covoiturages.
     *
     * La route /resultats est appelée en GET après la saisie
     * du formulaire de recherche.
     *
     * Le contrôleur commence par lire les critères transmis
     * dans la chaîne de requête, puis il applique plusieurs contrôles :
     * - présence des champs indispensables ;
     * - format de la date ;
     * - format de l'heure si elle est fournie ;
     * - conversion des champs numériques seulement si leur valeur
     *   est exploitable.
     *
     * Si les critères sont valides, la méthode appelle ensuite
     * le service de persistance pour récupérer les covoiturages.
     *
     * Quand aucun covoiturage n'est trouvé,
     * une date alternative la plus proche peut être proposée.
     *
     * @param Request $requete Requête HTTP courante.
     * @param PersistanceCovoituragePostgresql $persistance
     *        Service chargé de la recherche des covoiturages en base.
     *
     * @return Response Réponse HTTP contenant le rendu HTML de la page de résultats.
     */
    #[Route('/resultats', name: 'resultats', methods: ['GET'])]
    public function index(Request $requete, PersistanceCovoituragePostgresql $persistance): Response
    {
        /*
         * On lit les champs principaux transmis dans l'URL,
         * puis on supprime les espaces inutiles.
         *
         * Ces trois champs sont indispensables
         * pour lancer une recherche exploitable :
         * ville de départ, ville d'arrivée et date.
         */
        $villeDepart = trim((string) $requete->query->get('ville_depart', ''));
        $villeArrivee = trim((string) $requete->query->get('ville_arrivee', ''));
        $date = trim((string) $requete->query->get('date', ''));

        /*
         * Heure souhaitée
         *
         * La valeur est d'abord lue comme texte.
         * Si aucun horaire n'est fourni,
         * on garde null pour signaler qu'il n'y a pas
         * de contrainte horaire précise.
         */
        $heureSouhaiteeBrut = trim((string) $requete->query->get('heure_souhaitee', ''));
        $heureSouhaitee = ('' === $heureSouhaiteeBrut) ? null : $heureSouhaiteeBrut;

        /*
         * Énergie
         *
         * Si une valeur est fournie,
         * on la passe en majuscules pour rester cohérente
         * avec les valeurs stockées en base.
         * Sinon, on garde null.
         */
        $energieBrut = trim((string) $requete->query->get('energie', ''));
        $energie = ('' === $energieBrut) ? null : strtoupper($energieBrut);

        /*
         * Prix maximum
         *
         * On ne conserve la valeur que si elle est composée
         * uniquement de chiffres et strictement positive.
         * Sinon, on garde null pour ignorer ce filtre.
         */
        $prixMaxTexte = trim((string) $requete->query->get('prix_max', ''));
        $prixMax = (ctype_digit($prixMaxTexte) && (int) $prixMaxTexte > 0) ? (int) $prixMaxTexte : null;

        /*
         * Âge maximum du véhicule
         *
         * Même logique que pour le prix :
         * on garde seulement un entier strictement positif.
         */
        $ageMaxVoitureTexte = trim((string) $requete->query->get('age_max_voiture', ''));
        $ageMaxVoiture = (ctype_digit($ageMaxVoitureTexte) && (int) $ageMaxVoitureTexte > 0) ? (int) $ageMaxVoitureTexte : null;

        /*
         * Note minimale
         *
         * La note n'est retenue que si elle correspond
         * à un entier compris entre 1 et 5.
         */
        $noteMinTexte = trim((string) $requete->query->get('note_min', ''));
        $noteMin = (ctype_digit($noteMinTexte) && (int) $noteMinTexte >= 1 && (int) $noteMinTexte <= 5)
            ? (int) $noteMinTexte
            : null;

        /*
         * Durée maximale
         *
         * La valeur est retenue seulement si elle correspond
         * à un entier compris entre 10 et 1440 minutes.
         * Cela permet de filtrer sur une durée raisonnable
         * tout en évitant des valeurs incohérentes.
         */
        $dureeMaxMinutesTexte = trim((string) $requete->query->get('duree_max_minutes', ''));
        $dureeMaxMinutes = (ctype_digit($dureeMaxMinutesTexte)
            && (int) $dureeMaxMinutesTexte >= 10
            && (int) $dureeMaxMinutesTexte <= 1440)
            ? (int) $dureeMaxMinutesTexte
            : null;

        /*
         * On regroupe les critères dans un tableau
         * pour pouvoir les réutiliser facilement dans Twig,
         * notamment si la recherche doit être réaffichée
         * avec un message d'erreur.
         */
        $criteres = [
            'ville_depart' => $villeDepart,
            'ville_arrivee' => $villeArrivee,
            'date' => $date,
            'heure_souhaitee' => $heureSouhaitee,
            'prix_max' => $prixMax,
            'energie' => $energie,
            'age_max_voiture' => $ageMaxVoiture,
            'duree_max_minutes' => $dureeMaxMinutes,
            'note_min' => $noteMin,
        ];

        /*
         * Validation minimale des champs obligatoires
         *
         * Si la ville de départ, la ville d'arrivée
         * ou la date manquent, on ne lance pas la recherche.
         * On renvoie directement la page recherche
         * avec un message lisible et les critères déjà saisis.
         */
        if ('' === $villeDepart || '' === $villeArrivee || '' === $date) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Veuillez renseigner ville de départ, ville d’arrivée et date.',
                'criteres' => $criteres,
            ]);
        }

        /*
         * Contrôle du format de date
         *
         * Le champ date vient d'un input type="date".
         * Le format attendu est donc AAAA-MM-JJ.
         */
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Date invalide (format attendu : AAAA-MM-JJ).',
                'criteres' => $criteres,
            ]);
        }

        /*
         * Contrôle du format de l'heure souhaitée
         *
         * Si une heure est fournie, elle doit respecter
         * le format HH:MM attendu par un input type="time".
         */
        if (null !== $heureSouhaitee && !preg_match('/^\d{2}:\d{2}$/', $heureSouhaitee)) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Heure invalide (format attendu : HH:MM).',
                'criteres' => $criteres,
            ]);
        }

        /*
         * Conversion de la date texte en objet DateTimeImmutable
         *
         * Ce type d'objet date permet ensuite de manipuler
         * la journée et les horaires de manière plus fiable.
         *
         * Si la conversion échoue,
         * on renvoie la page de recherche avec un message d'erreur.
         */
        try {
            $dateObjet = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Date invalide.',
                'criteres' => $criteres,
            ]);
        }

        /*
         * Préparation éventuelle d'une plage horaire
         *
         * Si l'utilisateur a donné une heure souhaitée,
         * on construit une plage autour de cet horaire
         * pour rester plus souple dans la recherche.
         *
         * L'idée est de ne pas imposer une heure exacte,
         * mais une fenêtre de tolérance.
         */
        $heureMin = null;
        $heureMax = null;

        if (null !== $heureSouhaitee) {
            /*
             * La marge retenue est de 60 minutes avant
             * et 60 minutes après l'heure demandée.
             */
            $margeMinutes = 60;

            /*
             * On construit d'abord la date et l'heure cibles
             * à partir de la journée recherchée
             * et de l'heure souhaitée.
             */
            $dateHeureCible = $dateObjet->setTime(
                (int) substr($heureSouhaitee, 0, 2),
                (int) substr($heureSouhaitee, 3, 2),
                0
            );

            /*
             * On calcule ensuite la borne basse
             * et la borne haute de la plage horaire.
             */
            $dateHeureMin = $dateHeureCible->modify('-' . $margeMinutes . ' minutes');
            $dateHeureMax = $dateHeureCible->modify('+' . $margeMinutes . ' minutes');

            /*
             * On garde la plage dans la même journée
             * pour éviter de déborder sur la veille
             * ou le lendemain.
             */
            $debutJournee = $dateObjet->setTime(0, 0, 0);
            $finJournee = $dateObjet->setTime(23, 59, 59);

            if ($dateHeureMin < $debutJournee) {
                $dateHeureMin = $debutJournee;
            }

            if ($dateHeureMax > $finJournee) {
                $dateHeureMax = $finJournee;
            }

            /*
             * On convertit enfin les bornes au format HH:MM
             * pour les transmettre au service de persistance.
             */
            $heureMin = $dateHeureMin->format('H:i');
            $heureMax = $dateHeureMax->format('H:i');
        }

        /*
         * On ajoute cette plage au tableau des critères.
         * Cela permet aussi de la réafficher dans la vue si besoin.
         */
        $criteres['heure_min'] = $heureMin;
        $criteres['heure_max'] = $heureMax;

        /*
         * Recherche des covoiturages
         *
         * Le contrôleur délègue la lecture à la couche de persistance
         * en lui transmettant les critères préparés.
         *
         * Si aucune heure n'a été fournie,
         * les bornes horaires restent null.
         */
        $resultats = $persistance->rechercherCovoiturages(
            $villeDepart,
            $villeArrivee,
            $dateObjet,
            $heureMin,
            $heureMax,
            $prixMax,
            $energie,
            $ageMaxVoiture,
            $noteMin,
            $dureeMaxMinutes
        );

        /*
         * Proposition d'une date alternative
         *
         * Si aucun covoiturage n'est trouvé,
         * on demande au service de rechercher
         * la date disponible la plus proche
         * avec les mêmes critères.
         */
        $dateAlternative = null;

        if (0 === count($resultats)) {
            $dateAlternative = $persistance->trouverDateDisponibleLaPlusProche(
                $villeDepart,
                $villeArrivee,
                $dateObjet,
                $heureMin,
                $heureMax,
                $prixMax,
                $energie,
                $ageMaxVoiture,
                $noteMin,
                $dureeMaxMinutes
            );
        }

        /*
         * Rendu de la page résultats
         *
         * La vue reçoit :
         * - la liste des covoiturages trouvés ;
         * - les critères utilisés ;
         * - une éventuelle date alternative si aucun trajet n'a été trouvé.
         */
        return $this->render('resultats/index.html.twig', [
            'resultats' => $resultats,
            'criteres' => $criteres,
            'date_alternative' => $dateAlternative,
        ]);
    }
}