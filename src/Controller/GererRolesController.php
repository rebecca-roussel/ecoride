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

final class GererRolesController extends AbstractController
{
    /*
      PLAN (GererRolesController) :

      1) Accès sécurisé
         - utiliser SessionUtilisateur 
         - si pas connecté rediriger vers la connexion

      2) Afficher la page Gérer mes rôles (GET)
         - afficher les rôles actuels
         - tracer l’ouverture de page dans MongoDB

      3) Enregistrer les rôles (POST)
         - protéger l’action avec un jeton CSRF
         - interdire 0 rôle
         - enregistrer en BDD
         - mettre à jour la session
         - tracer l’évènement dans MongoDB
         - rediriger (POST-Redirect-GET)
    */

    #[Route('/tableau-de-bord/roles', name: 'gerer_roles', methods: ['GET', 'POST'])]
    public function index(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur,
        JournalEvenements $journalEvenements,
    ): Response {
        // Sécurité : connecté ?
        $utilisateur = $sessionUtilisateur->obtenirUtilisateurConnecte();
        if ($utilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        /* Valeurs d’affichage */
        $donnees = $persistanceUtilisateur->obtenirDonneesTableauDeBord($idUtilisateur);
        if ($donnees === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $rolePassager = ((int) ($donnees['role_passager'] ?? 0)) === 1;
        $roleChauffeur = ((int) ($donnees['role_chauffeur'] ?? 0)) === 1;

        // POST : traitement du formulaire
        if ($requete->isMethod('POST')) {
            // 2.a) CSRF
            $csrf = (string) $requete->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('gerer_roles', $csrf)) {
                $this->addFlash('erreur', 'Jeton de sécurité invalide. Merci de réessayer.');
                return $this->redirectToRoute('gerer_roles');
            }

            // Lecture des champs 
            $rolePassager = $requete->request->has('role_passager');
            $roleChauffeur = $requete->request->has('role_chauffeur');

            // pas 0 rôle
            if (!$rolePassager && !$roleChauffeur) {
                $this->addFlash('erreur', 'Vous devez garder au moins un rôle : chauffeur ou passager.');

                return $this->render('roles/index.html.twig', [
                    'role_passager' => $rolePassager,
                    'role_chauffeur' => $roleChauffeur,
                ]);
            }

            // Enregistrement BDD
            try {
                $persistanceUtilisateur->mettreAJourRoles($idUtilisateur, $roleChauffeur, $rolePassager);
            } catch (RuntimeException $exception) {
                $this->addFlash('erreur', $exception->getMessage());

                return $this->render('roles/index.html.twig', [
                    'role_passager' => $rolePassager,
                    'role_chauffeur' => $roleChauffeur,
                ]);
            }

            // Mise à jour session
            $sessionUtilisateur->mettreAJourRolesUtilisateurConnecte($roleChauffeur, $rolePassager);

            // Journal MongoDB
            $journalEvenements->enregistrer(
                'roles_mis_a_jour',
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

        // GET : journal + affichage
        $journalEvenements->enregistrer(
            'page_gerer_roles_ouverte',
            'utilisateur',
            $idUtilisateur
        );

        return $this->render('roles/index.html.twig', [
            'role_passager' => $rolePassager,
            'role_chauffeur' => $roleChauffeur,
        ]);
    }
}


