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
    public function index(Request $request, PersistanceCovoituragePostgresql $persistance): Response
    {
        $criteres = [
            'depart' => trim((string) $request->query->get('depart', '')),
            'arrivee' => trim((string) $request->query->get('arrivee', '')),
            'date_depart' => trim((string) $request->query->get('date_depart', '')),
            'heure_depart' => trim((string) $request->query->get('heure_depart', '')), // pas utilisé pour l’instant
        ];

        $covoiturages = $persistance->rechercher(
            $criteres['depart'] !== '' ? $criteres['depart'] : null,
            $criteres['arrivee'] !== '' ? $criteres['arrivee'] : null,
            $criteres['date_depart'] !== '' ? $criteres['date_depart'] : null
        );

        return $this->render('resultats/index.html.twig', [
            'criteres' => $criteres,
            'covoiturages' => $covoiturages,
        ]);
    }
}
