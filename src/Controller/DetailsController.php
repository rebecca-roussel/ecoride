<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceCovoituragePostgresql;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DetailsController extends AbstractController
{
    #[Route('/details', name: 'details')]
    public function index(Request $requeteHttp, PersistanceCovoituragePostgresql $persistance): Response
    {
        // On lit l'id dans l'URL : /details?id=7
        $id = $requeteHttp->query->getInt('id', 0);

        if ($id <= 0) {
            // Pas d'id (ou id invalide) = 404
            throw $this->createNotFoundException('Identifiant de covoiturage invalide.');
        }

        $detail = $persistance->obtenirDetailCovoiturageParId($id);

        if (null === $detail) {
            throw $this->createNotFoundException('Covoiturage introuvable.');
        }

        return $this->render('details/index.html.twig', [
            'detail' => $detail,
        ]);
    }
}
