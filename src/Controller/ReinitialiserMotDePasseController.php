<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceReinitialisationMotDePassePostgresql;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du parcours de réinitialisation du mot de passe.
 *
 * Cette classe gère le lien reçu par courriel,
 * l'affichage du formulaire de nouveau mot de passe
 * puis l'enregistrement du nouveau mot de passe si le jeton reste valide.
 *
 * Le contrôleur garde ici la logique HTTP :
 * lecture du jeton dans l'URL,
 * lecture du formulaire,
 * contrôle CSRF,
 * validation simple des champs,
 * messages d'interface
 * et redirection.
 *
 * La lecture du jeton et la mise à jour réelle dans PostgreSQL
 * sont déléguées à `PersistanceReinitialisationMotDePassePostgresql`.
 *
 * La journalisation MongoDB trace ici
 * une réinitialisation réellement effectuée.
 */
final class ReinitialiserMotDePasseController extends AbstractController
{
    /**
     * Affiche le formulaire de nouveau mot de passe
     * et traite sa soumission.
     *
     * En GET, la méthode vérifie d'abord que le jeton reste valide,
     * puis affiche la page.
     *
     * En POST, elle vérifie le jeton CSRF,
     * contrôle la cohérence du nouveau mot de passe
     * puis délègue la mise à jour à PostgreSQL.
     *
     * Une fois la réinitialisation réussie,
     * un événement `mot_de_passe_reinitialise` est enregistré dans MongoDB.
     */
    #[Route('/reinitialiser_mot_de_passe/{jeton}', name: 'reinitialiser_mot_de_passe', methods: ['GET', 'POST'])]
    public function index(
        string $jeton,
        Request $request,
        PersistanceReinitialisationMotDePassePostgresql $persistance,
        JournalEvenements $journalEvenements,
    ): Response {
        $jeton = trim($jeton);

        $ligne = $persistance->trouverJetonValide($jeton);

        if ($ligne === null) {
            return $this->render('reinitialiser_mot_de_passe/index.html.twig', [
                'jeton' => $jeton,
                'message' => null,
                'erreur' => 'Lien invalide ou expiré.',
            ]);
        }

        if ($request->isMethod('GET')) {
            return $this->render('reinitialiser_mot_de_passe/index.html.twig', [
                'jeton' => $jeton,
                'message' => null,
                'erreur' => null,
            ]);
        }

        if (!$this->isCsrfTokenValid('reinitialiser_mot_de_passe', (string) $request->request->get('_csrf_token'))) {
            return $this->render('reinitialiser_mot_de_passe/index.html.twig', [
                'jeton' => $jeton,
                'message' => null,
                'erreur' => 'Requête invalide (CSRF).',
            ]);
        }

        $motDePasse = (string) $request->request->get('mot_de_passe', '');
        $confirmation = (string) $request->request->get('mot_de_passe_confirmation', '');

        if ($motDePasse === '' || $motDePasse !== $confirmation || mb_strlen($motDePasse) < 8) {
            return $this->render('reinitialiser_mot_de_passe/index.html.twig', [
                'jeton' => $jeton,
                'message' => null,
                'erreur' => 'Mot de passe invalide (8 caractères min) ou confirmation différente.',
            ]);
        }

        try {
            $idReinitialisation = (int) ($ligne['id_reinitialisation'] ?? 0);
            $idUtilisateur = (int) ($ligne['id_utilisateur'] ?? 0);

            $persistance->utiliserJetonEtChangerMotDePasse(
                $idReinitialisation,
                $idUtilisateur,
                $motDePasse
            );

            if ($idUtilisateur > 0) {
                $journalEvenements->enregistrer(
                    'mot_de_passe_reinitialise',
                    'utilisateur',
                    $idUtilisateur,
                    [
                        'id_reinitialisation' => $idReinitialisation,
                    ]
                );
            }

            $this->addFlash('succes', 'Mot de passe mis à jour. Vous pouvez vous connecter.');

            return $this->redirectToRoute('connexion');
        } catch (Throwable) {
            return $this->render('reinitialiser_mot_de_passe/index.html.twig', [
                'jeton' => $jeton,
                'message' => null,
                'erreur' => 'Erreur technique : réinitialisation impossible.',
            ]);
        }
    }
}