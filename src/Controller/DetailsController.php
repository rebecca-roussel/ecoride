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
    #[Route('/details', name: 'details', methods: ['GET'])]
    public function index(Request $requeteHttp, PersistanceCovoituragePostgresql $persistance): Response
    {
        $id = $requeteHttp->query->getInt('id', 0);

        if ($id <= 0) {
            throw $this->createNotFoundException('Identifiant de covoiturage invalide.');
        }

        $detail = $persistance->obtenirDetailCovoiturageParId($id);

        if (null === $detail) {
            throw $this->createNotFoundException('Covoiturage introuvable.');
        }

        $avisChauffeur = [];

        $idChauffeur = (int) ($detail['id_chauffeur'] ?? 0);
        if ($idChauffeur > 0) {
            $avisChauffeur = $persistance->obtenirAvisValidesDuChauffeur($idChauffeur, 5);
        }

        return $this->render('details/index.html.twig', [
            'detail' => $detail,
            'avis_chauffeur' => $avisChauffeur,
        ]);
    }
}
