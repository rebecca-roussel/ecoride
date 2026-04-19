<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceCreditsPostgresql;
use App\Service\SessionUtilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page crédits.
 *
 * Cette classe affiche le solde de crédits de l'utilisateur connecté
 * ainsi que la liste de ses opérations récentes.
 *
 * Le contrôleur garde ici le rôle lié au web :
 * vérifier la session,
 * choisir la redirection si besoin,
 * puis transmettre les données à Twig pour l'affichage.
 *
 * Les lectures dans PostgreSQL sont déléguées
 * à `PersistanceCreditsPostgresql`.
 */
final class CreditsController extends AbstractController
{
    /**
     * Affiche la page des crédits.
     *
     * La méthode vérifie d'abord qu'un utilisateur est bien connecté.
     * Si ce n'est pas le cas, l'accès est redirigé vers la connexion.
     *
     * Si la session est valide, la méthode lit :
     * - le solde actuel de crédits ;
     * - les dernières opérations enregistrées.
     *
     * @param SessionUtilisateur $sessionUtilisateur
     *        Service qui permet de lire l'utilisateur connecté en session.
     * @param PersistanceCreditsPostgresql $persistanceCredits
     *        Service de lecture PostgreSQL pour les crédits.
     *
     * @return Response Réponse HTML rendue par Twig ou redirection.
     */
    #[Route('/credits', name: 'credits', methods: ['GET'])]
    public function index(
        SessionUtilisateur $sessionUtilisateur,
        PersistanceCreditsPostgresql $persistanceCredits
    ): Response {
        /*
         * La session contient les informations minimales
         * sur l'utilisateur actuellement connecté.
         */
        $utilisateur = $sessionUtilisateur->obtenirUtilisateurConnecte();

        if ($utilisateur === null) {
            $this->addFlash('erreur', 'Veuillez vous connecter pour accéder à cette page.');

            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        /*
         * Le solde correspond au nombre de crédits actuellement disponibles.
         * Les opérations permettent d'expliquer les mouvements récents
         * du compte de crédits.
         */
        $soldeCredits = $persistanceCredits->obtenirSoldeCredits($idUtilisateur);
        $operations = $persistanceCredits->listerOperationsCredits($idUtilisateur, 20);

        return $this->render('credits/index.html.twig', [
            'solde_credits' => $soldeCredits,
            'operations' => $operations,
        ]);
    }
}
