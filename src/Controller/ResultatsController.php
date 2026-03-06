<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceCovoituragePostgresql;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResultatsController extends AbstractController
{
    /*
      PLAN (ResultatsController) :

      1) Rôle du contrôleur
         - je reçois les paramètres de recherche 
         - je fais une validation minimale (formats date/heure)
         - j’appelle la BDD via PersistanceCovoituragePostgresql
         - j’envoie les résultats au Twig resultats/index.html.twig

      2) Important
         - les noms des paramètres doivent rester alignés avec la BDD et le formulaire
         - la BDD est la vérité, le contrôleur orchestre juste la recherche

      3) Petit confort UX
         - l’utilisateur donne une heure souhaitée
         - moi je transforme ça en plage horaire (heure_min / heure_max) avec une marge
         - si rien dans la plage, je relance une recherche sans filtre heure 
    */

    #[Route('/resultats', name: 'resultats', methods: ['GET'])]
    public function index(Request $requete, PersistanceCovoituragePostgresql $persistance): Response
    {
        /*
          1) Lecture des paramètres socle
          - ce sont les champs indispensables pour lancer une recherche
          - je trim pour éviter les espaces invisibles
        */
        $villeDepart = trim((string) $requete->query->get('ville_depart', ''));
        $villeArrivee = trim((string) $requete->query->get('ville_arrivee', ''));
        $date = trim((string) $requete->query->get('date', ''));

        /*
          2) Heure souhaitée 
          - l’utilisateur renseigne une heure précise
          - mais la recherche BDD fonctionne mieux en plage horaire
          - donc je convertit plus bas en heure_min / heure_max
        */
        $heureSouhaiteeBrut = trim((string) $requete->query->get('heure_souhaitee', ''));
        $heureSouhaitee = ('' === $heureSouhaiteeBrut) ? null : $heureSouhaiteeBrut;

        /*
          3) Filtres optionnels
          - si le champ est vide ou invalide, je mets null
        */

        // Energie : je mets en majuscules pour matcher les valeurs de la BDD
        $energieBrut = trim((string) $requete->query->get('energie', ''));
        $energie = ('' === $energieBrut) ? null : strtoupper($energieBrut);

        // Prix max : uniquement si entier > 0
        $prixMaxTexte = trim((string) $requete->query->get('prix_max', ''));
        $prixMax = (ctype_digit($prixMaxTexte) && (int) $prixMaxTexte > 0) ? (int) $prixMaxTexte : null;

        // Âge max voiture : uniquement si entier > 0
        $ageMaxVoitureTexte = trim((string) $requete->query->get('age_max_voiture', ''));
        $ageMaxVoiture = (ctype_digit($ageMaxVoitureTexte) && (int) $ageMaxVoitureTexte > 0) ? (int) $ageMaxVoitureTexte : null;

        // Note min : entier entre 1 et 5
        $noteMinTexte = trim((string) $requete->query->get('note_min', ''));
        $noteMin = (ctype_digit($noteMinTexte) && (int) $noteMinTexte >= 1 && (int) $noteMinTexte <= 5) ? (int) $noteMinTexte : null;

        // Durée max : entier entre 10 et 1440
        $dureeMaxMinutesTexte = trim((string) $requete->query->get('duree_max_minutes', ''));
        $dureeMaxMinutes = (ctype_digit($dureeMaxMinutesTexte) && (int) $dureeMaxMinutesTexte >= 10 && (int) $dureeMaxMinutesTexte <= 1440) ? (int) $dureeMaxMinutesTexte : null;

        /* 4) Critères*/
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
          5) Validation minimale
          - si les champs indispensables ne sont pas là, je renvoie sur la page de recherche
          - j’affiche un message, parce que c’est la page recherche qui gère les erreurs
        */
        if ('' === $villeDepart || '' === $villeArrivee || '' === $date) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Veuillez renseigner ville de départ, ville d’arrivée et date.',
                'criteres' => $criteres,
            ]);
        }

        // Date attendue : YYYY-MM-DD (input type="date")
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Date invalide (format attendu : AAAA-MM-JJ).',
                'criteres' => $criteres,
            ]);
        }

        // Heure souhaitée attendue : HH:MM (input type="time") 
        if (null !== $heureSouhaitee && !preg_match('/^\d{2}:\d{2}$/', $heureSouhaitee)) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Heure invalide (format attendu : HH:MM).',
                'criteres' => $criteres,
            ]);
        }

        // Conversion de la date string en objet DateTimeImmutable
        try {
            $dateObjet = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Date invalide.',
                'criteres' => $criteres,
            ]);
        }

        /*
          6) Conversion de l'heure souhaitée en plage [heure_min, heure_max]
          - le but est d'être tolérante car les gens ne tapent pas pile l’heure
          - margeMinutes réglable, ici 60 minutes
          - je bloque la plage dans la journée 
        */
        $heureMin = null;
        $heureMax = null;

        if (null !== $heureSouhaitee) {
            $margeMinutes = 60;

            $dateHeureCible = $dateObjet->setTime(
                (int) substr($heureSouhaitee, 0, 2),
                (int) substr($heureSouhaitee, 3, 2),
                0
            );

            $dateHeureMin = $dateHeureCible->modify('-' . $margeMinutes . ' minutes');
            $dateHeureMax = $dateHeureCible->modify('+' . $margeMinutes . ' minutes');

            // Je garde tout dans la même journée 
            $debutJournee = $dateObjet->setTime(0, 0, 0);
            $finJournee = $dateObjet->setTime(23, 59, 59);

            if ($dateHeureMin < $debutJournee) {
                $dateHeureMin = $debutJournee;
            }
            if ($dateHeureMax > $finJournee) {
                $dateHeureMax = $finJournee;
            }

            $heureMin = $dateHeureMin->format('H:i');
            $heureMax = $dateHeureMax->format('H:i');
        }

        // Garde la plage dans criteres 
        $criteres['heure_min'] = $heureMin;
        $criteres['heure_max'] = $heureMax;

        /*
          7) Appel BDD via Persistance
          - recherche “stricte” (avec plage horaire si l’utilisateur a donné une heure)
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

/* Proposition d'une date alternative la plus proche si aucun résultat */

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
          8) Affichage Twig
          - resultats : la liste des covoiturages
          - criteres : ce que l’utilisateur a cherché pour affichage + filtres
          - date_alternative : date proposée si aucun résultat
        */
        return $this->render('resultats/index.html.twig', [
            'resultats' => $resultats,
            'criteres' => $criteres,
            'date_alternative' => $dateAlternative,
        ]);
    }
}
