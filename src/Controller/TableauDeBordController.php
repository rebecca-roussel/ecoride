<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceUtilisateurPostgresql;
use App\Service\SessionUtilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du tableau de bord utilisateur.
 *
 * Cette classe gère l'affichage de la page principale
 * d'un utilisateur connecté après son authentification.
 *
 * Son rôle consiste à :
 * - vérifier qu'une session utilisateur existe ;
 * - charger les données utiles au tableau de bord ;
 * - préparer des informations lisibles pour l'interface ;
 * - renvoyer la vue Twig correspondante.
 *
 * La lecture des données est déléguée
 * au service PersistanceUtilisateurPostgresql.
 */
final class TableauDeBordController extends AbstractController
{
    /**
     * Affiche le tableau de bord de l'utilisateur connecté.
     *
     * La route /tableau-de-bord est accessible en GET.
     * Cette page est réservée à un utilisateur authentifié.
     *
     * Déroulement général :
     * la méthode vérifie d'abord qu'un identifiant utilisateur
     * est présent en session, charge ensuite les données utiles
     * au tableau de bord, transforme certains champs
     * pour l'affichage, puis transmet le tout à Twig.
     *
     * Les informations préparées pour la vue sont :
     * - le nombre de crédits ;
     * - les rôles actifs de l'utilisateur ;
     * - le pseudo ;
     * - l'URL de la photo de profil.
     *
     * @param SessionUtilisateur $sessionUtilisateur Service de lecture de la session utilisateur.
     * @param PersistanceUtilisateurPostgresql $persistanceUtilisateur
     *        Service de lecture des données du tableau de bord.
     *
     * @return Response Réponse HTTP contenant le rendu HTML du tableau de bord.
     */
    #[Route('/tableau-de-bord', name: 'tableau_de_bord', methods: ['GET'])]
    public function index(
        SessionUtilisateur $sessionUtilisateur,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur
    ): Response {
        /*
         * Contrôle d'accès
         *
         * Le tableau de bord est réservé à un utilisateur connecté.
         * On lit l'identifiant utilisateur depuis la session.
         * Si aucun identifiant n'est trouvé,
         * on redirige vers la page de connexion.
         */
        $idUtilisateur = $sessionUtilisateur->idUtilisateur();
        if (null === $idUtilisateur) {
            return $this->redirectToRoute('connexion');
        }

        /*
         * Lecture des données du tableau de bord
         *
         * On demande au service de persistance
         * les informations utiles à l'affichage.
         *
         * Cas particulier :
         * une session peut exister alors que le compte n'est plus trouvé
         * en base, par exemple après une suppression
         * ou une incohérence de session.
         *
         * Dans ce cas, on ferme la session
         * puis on redirige vers la connexion.
         */
        $utilisateur = $persistanceUtilisateur->obtenirDonneesTableauDeBord($idUtilisateur);
        if (null === $utilisateur) {
            $sessionUtilisateur->deconnecter();

            return $this->redirectToRoute('connexion');
        }

        /*
         * Préparation des rôles actifs
         *
         * On construit un tableau de libellés lisibles
         * à partir des rôles stockés en base.
         *
         * Le but est de transformer les valeurs techniques
         * role_chauffeur et role_passager
         * en texte directement exploitable dans la vue.
         */
        $rolesActifs = [];

        if ((int) ($utilisateur['role_chauffeur'] ?? 0) === 1) {
            $rolesActifs[] = 'Chauffeur';
        }

        if ((int) ($utilisateur['role_passager'] ?? 0) === 1) {
            $rolesActifs[] = 'Passager';
        }

        /*
         * Préparation des données envoyées à Twig
         *
         * - credits : nombre de crédits du compte ;
         * - roles_actifs : chaîne lisible pour l'interface ;
         * - pseudo : pseudo utilisateur affiché sur la page ;
         * - photo : URL finale de la photo de profil.
         *
         * Si aucun rôle actif n'est trouvé,
         * on affiche un tiret long pour garder
         * une valeur lisible dans l'interface.
         */
        $donnees = [
            'credits' => (int) ($utilisateur['credits'] ?? 0),
            'roles_actifs' => $rolesActifs !== [] ? implode(' · ', $rolesActifs) : '—',
            'pseudo' => (string) ($utilisateur['pseudo'] ?? 'EcoRider'),
            'photo' => $persistanceUtilisateur->urlPhotoProfil($utilisateur['photo_path'] ?? null),
        ];

        /*
         * Rendu de la vue
         *
         * Le contrôleur transmet les données préparées
         * au template Twig du tableau de bord.
         */
        return $this->render('tableau_de_bord/index.html.twig', [
            'donnees' => $donnees,
        ]);
    }
}