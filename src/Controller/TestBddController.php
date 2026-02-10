<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceCovoituragePostgresql;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestBddController extends AbstractController
{
    #[Route('/test-bdd', name: 'test_bdd', methods: ['GET'])]
    public function index(PersistanceCovoituragePostgresql $persistance): Response
    {
        $resultats = $persistance->rechercher(null, null, null);

        return $this->json([
            'nb_covoiturages' => count($resultats),
            'exemple' => $resultats[0] ?? null,
        ]);
    }
}
