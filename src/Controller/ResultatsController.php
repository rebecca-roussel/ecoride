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
    #[Route('/resultats', name: 'resultats', methods: ['GET'])]
    public function index(Request $requete, PersistanceCovoituragePostgresql $persistance): Response
    {
        // 1) Lecture des paramètres "socle" (alignés BDD : ville_depart, ville_arrivee, date)
        $villeDepart = trim((string) $requete->query->get('ville_depart', ''));
        $villeArrivee = trim((string) $requete->query->get('ville_arrivee', ''));
        $date = trim((string) $requete->query->get('date', ''));

        // 2) Heure souhaitée (UX) -> on la convertit en plage horaire (heure_min / heure_max)
        $heureSouhaiteeBrut = trim((string) $requete->query->get('heure_souhaitee', ''));
        $heureSouhaitee = ('' === $heureSouhaiteeBrut) ? null : $heureSouhaiteeBrut;

        // 3) Filtres optionnels
        $energieBrut = trim((string) $requete->query->get('energie', ''));
        $energie = ('' === $energieBrut) ? null : strtoupper($energieBrut);

        $prixMaxBrut = $requete->query->getInt('prix_max');
        $prixMax = ($prixMaxBrut > 0) ? $prixMaxBrut : null;

        $ageMaxVoitureBrut = $requete->query->getInt('age_max_voiture');
        $ageMaxVoiture = ($ageMaxVoitureBrut > 0) ? $ageMaxVoitureBrut : null;

        $noteMinBrut = $requete->query->getInt('note_min');
        $noteMin = ($noteMinBrut >= 1 && $noteMinBrut <= 5) ? $noteMinBrut : null;

        // 4) Critères (pour re-remplir le formulaire côté Twig)
        $criteres = [
            'ville_depart' => $villeDepart,
            'ville_arrivee' => $villeArrivee,
            'date' => $date,
            'heure_souhaitee' => $heureSouhaitee,
            'prix_max' => $prixMax,
            'energie' => $energie,
            'age_max_voiture' => $ageMaxVoiture,
            'note_min' => $noteMin,
        ];

        // 5) Validation minimale (pas de message d’erreur sur l’accueil, mais ici oui : page recherche)
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

        // Heure souhaitée attendue : HH:MM (input type="time") si renseignée
        if (null !== $heureSouhaitee && !preg_match('/^\d{2}:\d{2}$/', $heureSouhaitee)) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Heure invalide (format attendu : HH:MM).',
                'criteres' => $criteres,
            ]);
        }

        try {
            $dateObjet = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return $this->render('recherche/index.html.twig', [
                'message_erreur' => 'Date invalide.',
                'criteres' => $criteres,
            ]);
        }

        // 6) Conversion "heure_souhaitee" -> plage [heure_min, heure_max] (au plus proche, pas exact)
        $heureMin = null;
        $heureMax = null;

        if (null !== $heureSouhaitee) {
            $margeMinutes = 60; // réglage simple : ± 60 minutes

            $dateHeureCible = $dateObjet->setTime(
                (int) substr($heureSouhaitee, 0, 2),
                (int) substr($heureSouhaitee, 3, 2),
                0
            );

            $dateHeureMin = $dateHeureCible->modify('-' . $margeMinutes . ' minutes');
            $dateHeureMax = $dateHeureCible->modify('+' . $margeMinutes . ' minutes');

            // On bloque dans la journée (évite veille/lendemain)
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

        // On garde la plage dans criteres (utile pour afficher “entre … et …” si tu veux)
        $criteres['heure_min'] = $heureMin;
        $criteres['heure_max'] = $heureMax;

        // 7) Appel BDD (Persistance)
        $resultats = $persistance->rechercherCovoiturages(
            $villeDepart,
            $villeArrivee,
            $dateObjet,
            $heureMin,
            $heureMax,
            $prixMax,
            $energie,
            $ageMaxVoiture,
            $noteMin
        );

        return $this->render('resultats/index.html.twig', [
            'resultats' => $resultats,
            'criteres' => $criteres,
        ]);
    }
}
