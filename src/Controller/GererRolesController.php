<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceUtilisateurPostgresql;
use App\Service\SessionUtilisateur;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de gestion des rôles utilisateur.
 *
 * Cette classe permet à un utilisateur connecté
 * de consulter ses rôles actuels
 * puis de mettre à jour ses rôles publics :
 * passager et chauffeur.
 *
 * Le contrôleur garde ici le parcours HTTP :
 * lecture de la requête,
 * contrôle du jeton CSRF,
 * messages d'erreur ou de succès,
 * mise à jour de la session
 * et rendu Twig.
 *
 * L'écriture dans PostgreSQL est déléguée
 * à `PersistanceUtilisateurPostgresql`.
 *
 * La journalisation MongoDB n'intervient ici
 * qu'après une modification réellement réussie.
 */
final class GererRolesController extends AbstractController
{
    /**
     * Affiche la page de gestion des rôles
     * et traite la soumission du formulaire.
     *
     * En GET, la méthode lit les rôles actuels
     * puis affiche le formulaire.
     *
     * En POST, elle lit les cases cochées,
     * vérifie le jeton CSRF,
     * empêche la désactivation simultanée des deux rôles,
     * enregistre le changement en base,
     * met à jour la session
     * puis journalise l'action dans MongoDB.
     *
     * CSRF signifie Cross-Site Request Forgery.
     * C'est un mécanisme de protection
     * qui sert à vérifier qu'un formulaire envoyé
     * provient bien de l'application.
     *
     * @param Request $requete Requête HTTP courante.
     * @param SessionUtilisateur $sessionUtilisateur
     *        Service qui lit et met à jour la session utilisateur.
     * @param PersistanceUtilisateurPostgresql $persistanceUtilisateur
     *        Service de lecture et d'écriture PostgreSQL pour l'utilisateur.
     * @param JournalEvenements $journalEvenements
     *        Service qui enregistre les événements significatifs dans MongoDB.
     *
     * @return Response Réponse HTML rendue par Twig ou redirection.
     */
    #[Route('/tableau-de-bord/roles', name: 'gerer_roles', methods: ['GET', 'POST'])]
    public function index(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur,
        JournalEvenements $journalEvenements,
    ): Response {
        /*
         * Cette page suppose un utilisateur authentifié.
         * Sans session valide, on renvoie vers la connexion.
         */
        $utilisateur = $sessionUtilisateur->obtenirUtilisateurConnecte();
        if ($utilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        /*
         * On lit les données minimales du tableau de bord
         * pour retrouver les rôles actuellement enregistrés.
         */
        $donnees = $persistanceUtilisateur->obtenirDonneesTableauDeBord($idUtilisateur);
        if ($donnees === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        /*
         * Les rôles reviennent ici sous forme entière 0 ou 1.
         * On les convertit en booléens PHP pour simplifier
         * le reste du traitement et l'affichage Twig.
         */
        $rolePassager = ((int) ($donnees['role_passager'] ?? 0)) === 1;
        $roleChauffeur = ((int) ($donnees['role_chauffeur'] ?? 0)) === 1;

        if ($requete->isMethod('POST')) {
            /*
             * Le jeton CSRF protège l'action de modification.
             */
            $csrf = (string) $requete->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('gerer_roles', $csrf)) {
                $this->addFlash('erreur', 'Jeton de sécurité invalide. Merci de réessayer.');

                return $this->redirectToRoute('gerer_roles');
            }

            /*
             * Une case cochée existe dans la requête.
             * Une case décochée n'est pas envoyée.
             * `has()` permet donc de savoir si chaque rôle a été demandé.
             */
            $rolePassager = $requete->request->has('role_passager');
            $roleChauffeur = $requete->request->has('role_chauffeur');

            /*
             * L'utilisateur doit garder au moins un rôle public actif.
             * Sinon, la page est réaffichée avec le message d'erreur.
             */
            if (!$rolePassager && !$roleChauffeur) {
                $this->addFlash('erreur', 'Vous devez garder au moins un rôle : chauffeur ou passager.');

                return $this->render('roles/index.html.twig', [
                    'role_passager' => $rolePassager,
                    'role_chauffeur' => $roleChauffeur,
                ]);
            }

            try {
                /*
                 * L'enregistrement en base est centralisé
                 * dans la persistance PostgreSQL.
                 */
                $persistanceUtilisateur->mettreAJourRoles($idUtilisateur, $roleChauffeur, $rolePassager);
            } catch (RuntimeException $exception) {
                $this->addFlash('erreur', $exception->getMessage());

                return $this->render('roles/index.html.twig', [
                    'role_passager' => $rolePassager,
                    'role_chauffeur' => $roleChauffeur,
                ]);
            }

            /*
             * La session est mise à jour après succès
             * pour que l'application travaille immédiatement
             * avec les nouveaux rôles.
             */
            $sessionUtilisateur->mettreAJourRolesUtilisateurConnecte($roleChauffeur, $rolePassager);

            /*
             * La journalisation n'intervient qu'après un vrai changement réussi.
             * L'événement porte ici sur la modification des rôles utilisateur.
             */
            $journalEvenements->enregistrer(
                'role_utilisateur_modifie',
                'utilisateur',
                $idUtilisateur,
                [
                    'role_passager' => $rolePassager,
                    'role_chauffeur' => $roleChauffeur,
                ]
            );

            $this->addFlash('succes', 'Vos rôles ont bien été enregistrés.');

            return $this->redirectToRoute('gerer_roles');
        }

        return $this->render('roles/index.html.twig', [
            'role_passager' => $rolePassager,
            'role_chauffeur' => $roleChauffeur,
        ]);
    }
}