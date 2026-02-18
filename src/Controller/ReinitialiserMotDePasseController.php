<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceReinitialisationMotDePassePostgresql;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReinitialiserMotDePasseController extends AbstractController
{
    #[Route('/reinitialiser_mot_de_passe/{jeton}', name: 'reinitialiser_mot_de_passe', methods: ['GET', 'POST'])]
    public function index(
        string $jeton,
        Request $request,
        PersistanceReinitialisationMotDePassePostgresql $persistance,
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

        $persistance->utiliserJetonEtChangerMotDePasse(
            (int) $ligne['id_reinitialisation'],
            (int) $ligne['id_utilisateur'],
            $motDePasse
        );

        $this->addFlash('succes', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
        return $this->redirectToRoute('connexion');
    }
}
