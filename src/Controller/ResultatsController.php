<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResultatsController extends AbstractController
{
    #[Route('/resultats', name: 'resultats', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $criteres = [
            'depart' => trim((string) $request->query->get('depart', '')),
            'arrivee' => trim((string) $request->query->get('arrivee', '')),
            'date_depart' => (string) $request->query->get('date_depart', ''),
            'heure_depart' => (string) $request->query->get('heure_depart', ''),
        ];

        return $this->render('resultats/index.html.twig', [
            'criteres' => $criteres,
            'covoiturages' => [], // vide pour l’instant
        ]);
    }
}
