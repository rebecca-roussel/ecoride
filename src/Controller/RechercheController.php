<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RechercheController extends AbstractController
{
    #[Route('/recherche', name: 'recherche')]
    public function __invoke(Request $requete): Response
    {
        return $this->render('recherche/index.html.twig', [
            'criteres' => [
                'ville_depart' => (string) $requete->query->get('ville_depart', ''),
                'ville_arrivee' => (string) $requete->query->get('ville_arrivee', ''),
                'date' => (string) $requete->query->get('date', ''),
                'heure_min' => (string) $requete->query->get('heure_min', ''),
                'heure_max' => (string) $requete->query->get('heure_max', ''),
            ],
            'message_erreur' => (string) $requete->query->get('message_erreur', ''),
        ]);
    }
}
