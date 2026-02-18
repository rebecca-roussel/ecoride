<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceUtilisateurPostgresql;
use App\Service\SessionUtilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TableauDeBordController extends AbstractController
{
    #[Route('/tableau-de-bord', name: 'tableau_de_bord', methods: ['GET'])]
    public function index(
        SessionUtilisateur $sessionUtilisateur,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur
    ): Response {
        /*
          PLAN (TableauDeBordController) :

          1) Sécurité
             - si je ne suis pas connectée, je renvoie vers /connexion
             - je ne laisse pas quelqu’un accéder au tableau de bord “au hasard”

          2) Lecture de l’utilisateur en base
             - je prends l’id depuis la session
             - je recharge les infos depuis PostgreSQL (source de vérité)
             - comme ça, si la photo a été modifiée dans /profil, je la récupère bien ici

          3) Préparation des données pour Twig
             - crédits
             - rôles actifs (chauffeur / passager / ou les deux)
             - pseudo
             - photo : je transforme photo_path en URL affichable

          4) Affichage
             - j’envoie au template tableau_de_bord/index.html.twig
        */

        // 1) Sécurité : page réservée aux utilisateurs connectés
        $idUtilisateur = $sessionUtilisateur->idUtilisateur();
        if (null === $idUtilisateur) {
            return $this->redirectToRoute('connexion');
        }

        // 2) Je recharge les infos depuis la base
        //    si la photo a été changée dans /profil, je la récupère ici.
        $utilisateur = $persistanceUtilisateur->obtenirDonneesTableauDeBord($idUtilisateur);
        if (null === $utilisateur) {
            // Session incohérente (ex : utilisateur supprimé)
            $sessionUtilisateur->deconnecter();
            return $this->redirectToRoute('connexion');
        }

        // 3) Rôles actifs
        //    Un utilisateur peut avoir :
        //    - chauffeur
        //    - passager
        //    - ou les deux 
        $rolesActifs = [];

        if ((int) ($utilisateur['role_chauffeur'] ?? 0) === 1) {
            $rolesActifs[] = 'Chauffeur';
        }

        if ((int) ($utilisateur['role_passager'] ?? 0) === 1) {
            $rolesActifs[] = 'Passager';
        }

        // 3) Données finales envoyées à Twig
        $donnees = [
            'credits' => (int) ($utilisateur['credits'] ?? 0),
            'roles_actifs' => $rolesActifs !== [] ? implode(' · ', $rolesActifs) : '—',
            'pseudo' => (string) ($utilisateur['pseudo'] ?? 'EcoRider'),

            // Photo 
            'photo' => $persistanceUtilisateur->urlPhotoProfil($utilisateur['photo_path'] ?? null),
        ];

        // 4) Affichage
        return $this->render('tableau_de_bord/index.html.twig', [
            'donnees' => $donnees,
        ]);
    }
}
