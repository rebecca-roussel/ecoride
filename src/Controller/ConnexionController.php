<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConnexionPostgresql;
use App\Service\SessionUtilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConnexionController extends AbstractController
{
    #[Route('/connexion', name: 'connexion', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ConnexionPostgresql $connexion,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        // Si déjà connecté : inutile d'afficher la page
        if ($sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('tableau_de_bord');
        }

        $erreur = null;
        $emailSaisi = '';

        if ($request->isMethod('POST')) {
            $emailSaisi = trim((string) $request->request->get('email'));
            $motDePasse = (string) $request->request->get('mot_de_passe');

            if ('' === $emailSaisi || '' === $motDePasse) {
                $erreur = 'Email et mot de passe sont obligatoires.';
            } else {
                try {
                    $pdo = $connexion->obtenirPdo();

                    $stmt = $pdo->prepare('
                        SELECT id_utilisateur, pseudo, mot_de_passe_hash, statut
                        FROM utilisateur
                        WHERE email = :email
                        LIMIT 1
                    ');
                    $stmt->execute(['email' => $emailSaisi]);
                    $utilisateur = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (!$utilisateur) {
                        $erreur = 'Identifiants invalides.';
                    } elseif (($utilisateur['statut'] ?? '') !== 'ACTIF') {
                        $erreur = 'Compte suspendu.';
                    } elseif (!password_verify($motDePasse, (string) ($utilisateur['mot_de_passe_hash'] ?? ''))) {
                        $erreur = 'Identifiants invalides.';
                    } else {
                        $sessionUtilisateur->connecter((int) $utilisateur['id_utilisateur'], (string) $utilisateur['pseudo']);

                        return $this->redirectToRoute('tableau_de_bord');
                    }
                } catch (\PDOException) {
                    $erreur = 'Erreur lors de la connexion.';
                }
            }
        }

        return $this->render('connexion/index.html.twig', [
            'erreur' => $erreur,
            'email_saisi' => $emailSaisi,

            'utilisateur_connecte' => $sessionUtilisateur->estConnecte(),
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
        ]);
    }

    #[Route('/deconnexion', name: 'deconnexion', methods: ['POST', 'GET'])]
    public function deconnexion(SessionUtilisateur $sessionUtilisateur): Response
    {
        $sessionUtilisateur->deconnecter();

        return $this->redirectToRoute('accueil');
    }
}
