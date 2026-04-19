<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceCovoituragePostgresql;
use App\Service\SessionUtilisateur;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du parcours de participation à un covoiturage.
 *
 * Cette classe gère l'action déclenchée quand un utilisateur
 * souhaite réserver une place sur un covoiturage.
 *
 * Le contrôleur garde ici ce qui relève du web :
 * lecture de l'identifiant transmis dans l'URL,
 * contrôle de la session,
 * vérification du jeton CSRF,
 * messages flash et redirections.
 *
 * La vérification des règles de réservation
 * et l'écriture dans PostgreSQL sont déléguées
 * à `PersistanceCovoituragePostgresql`.
 *
 * @package App\Controller
 */
final class ParticiperCovoiturageController extends AbstractController
{
    /**
     * Traite la demande de participation à un covoiturage.
     *
     * Cette méthode s'exécute en POST.
     * Elle vérifie d'abord que l'identifiant du covoiturage est cohérent,
     * que l'utilisateur est bien connecté,
     * puis que le jeton CSRF du formulaire est valide.
     *
     * Une fois ces contrôles passés,
     * le contrôleur délègue la participation réelle
     * au service de persistance.
     *
     * @param int $id Identifiant du covoiturage transmis dans l'URL.
     * @param Request $requete Requête HTTP courante.
     * @param SessionUtilisateur $sessionUtilisateur Service de session utilisateur.
     * @param PersistanceCovoituragePostgresql $persistanceCovoiturage
     *        Service de persistance PostgreSQL des covoiturages.
     *
     * @return Response Redirection vers la page de détail ou vers une autre page du parcours.
     */
    #[Route('/participer/{id}', name: 'participer_covoiturage', methods: ['POST'])]
    public function participer(
        int $id,
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceCovoituragePostgresql $persistanceCovoiturage,
    ): Response {
        /*
         * Clause de garde :
         * l'identifiant du covoiturage doit être un entier positif.
         */
        if ($id <= 0) {
            $this->addFlash('erreur', 'Covoiturage invalide.');

            return $this->redirectToRoute('resultats');
        }

        /*
         * La réservation d'une place est réservée à un utilisateur connecté.
         */
        $utilisateur = $sessionUtilisateur->obtenirUtilisateurConnecte();
        if ($utilisateur === null) {
            $this->addFlash('erreur', 'Veuillez vous connecter pour participer.');

            return $this->redirectToRoute('connexion');
        }

        /*
         * L'identifiant du passager est relu depuis la session.
         * Si cette valeur manque, la session est considérée incohérente.
         */
        $idPassager = (int) ($utilisateur['id_utilisateur'] ?? 0);
        if ($idPassager <= 0) {
            $this->addFlash('erreur', 'Session invalide. Veuillez vous reconnecter.');

            return $this->redirectToRoute('connexion');
        }

        /*
         * Le jeton CSRF (Cross-Site Request Forgery) 
         * protège l'action contre une soumission frauduleuse.
         * Il sert à vérifier que le formulaire vient bien de l'application.
         */
        $jeton = (string) $requete->request->get('_token', '');
        if (!$this->isCsrfTokenValid('participer_covoiturage_' . $id, $jeton)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        try {
            $persistanceCovoiturage->participerAuCovoiturage($id, $idPassager);

            $this->addFlash('succes', 'Participation confirmée.');
        } catch (RuntimeException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('details', ['id' => $id]);
    }
}