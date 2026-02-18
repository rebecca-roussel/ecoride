<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GeocodageAdresse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiGeocodageController extends AbstractController
{
    #[Route('/api/geocodage/adresse', name: 'api_geocodage_adresse', methods: ['GET'])]
    public function adresse(Request $requete, GeocodageAdresse $geocodage): JsonResponse
    {
        $q = trim((string) $requete->query->get('q', ''));
        $limite = (int) $requete->query->get('limite', 5);

        if ($limite < 1) {
            $limite = 1;
        }

        if ($limite > 10) {
            $limite = 10;
        }

        if (mb_strlen($q) < 3) {
            return $this->json(['suggestions' => []]);
        }

        return $this->json([
            'suggestions' => $geocodage->chercher($q, $limite),
        ]);
    }
}
