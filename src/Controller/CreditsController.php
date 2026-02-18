<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceCreditsPostgresql;
use App\Service\SessionUtilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreditsController extends AbstractController
{
    #[Route('/credits', name: 'credits', methods: ['GET'])]
    public function index(
        SessionUtilisateur $sessionUtilisateur,
        PersistanceCreditsPostgresql $persistanceCredits,
        JournalEvenements $journalEvenements
    ): Response {
        $utilisateur = $sessionUtilisateur->obtenirUtilisateurConnecte();

        if ($utilisateur === null) {
            $this->addFlash('erreur', 'Veuillez vous connecter pour accéder à cette page.');
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        $soldeCredits = $persistanceCredits->obtenirSoldeCredits($idUtilisateur);
        $operations = $persistanceCredits->listerOperationsCredits($idUtilisateur, 20);

        $journalEvenements->enregistrer(
            'page_ouverte',
            'credits',
            $idUtilisateur,
            ['nb_operations' => count($operations)]
        );

        return $this->render('credits/index.html.twig', [
            'solde_credits' => $soldeCredits,
            'operations' => $operations,
        ]);
    }
}
