<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConnexionPostgresql;
use App\Service\SessionUtilisateur;
use PDO;
use PDOException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConnexionController extends AbstractController
{
    /*
      PLAN (ConnexionController) :

      1) Afficher la page de connexion (GET/POST)
         - si déjà connecté : redirection selon le profil

      2) POST : traiter le formulaire
         a) lire email + mot de passe
         b) charger l’utilisateur en base par email
         c) vérifier : existe ? statut ACTIF ? mot de passe ok ?
         d) calculer les rôles (chauffeur / passager / employé / administrateur)
         e) ouvrir la session via SessionUtilisateur->connecter(...)
         f) rediriger selon le profil

      3) Déconnexion
         - supprimer les clés de session
         - revenir à l’accueil
    */

    #[Route('/connexion', name: 'connexion', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ConnexionPostgresql $connexion,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        // 1) Déjà connecté : redirection selon le profil
        if ($sessionUtilisateur->estConnecte()) {
            if ($sessionUtilisateur->estAdmin()) {
                return $this->redirectToRoute('espace_administrateur');
            }

            if ($sessionUtilisateur->estEmploye()) {
                return $this->redirectToRoute('espace_employe');
            }

            return $this->redirectToRoute('tableau_de_bord');
        }

        // Variables d'affichage
        $erreur = null;
        $emailSaisi = '';

        if ($request->isMethod('POST')) {
            $emailSaisi = trim((string) $request->request->get('email', ''));
            $motDePasse = (string) $request->request->get('mot_de_passe', '');

            if ($emailSaisi === '' || $motDePasse === '') {
                $erreur = 'Email et mot de passe sont obligatoires.';
            } else {
                try {
                    $pdo = $connexion->obtenirPdo();

                    /*
                      On récupère l’utilisateur par email + ses infos utiles,
                      et on calcule 2 drapeaux via EXISTS :
                      - est_employe
                      - est_administrateur

                      ⚠️ PostgreSQL peut renvoyer :
                      - true/false
                      - 1/0
                      - 't'/'f'
                      donc on normalise ensuite.
                    */
                    $stmt = $pdo->prepare('
                        SELECT
                            u.id_utilisateur,
                            u.pseudo,
                            u.mot_de_passe_hash,
                            u.statut,
                            u.role_chauffeur,
                            u.role_passager,
                            EXISTS (
                                SELECT 1
                                FROM employe e
                                WHERE e.id_utilisateur = u.id_utilisateur
                            ) AS est_employe,
                            EXISTS (
                                SELECT 1
                                FROM administrateur a
                                WHERE a.id_utilisateur = u.id_utilisateur
                            ) AS est_administrateur
                        FROM utilisateur u
                        WHERE u.email = :email
                        LIMIT 1
                    ');

                    $stmt->execute(['email' => $emailSaisi]);
                    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!is_array($utilisateur)) {
                        $erreur = 'Identifiants invalides.';
                    } elseif (($utilisateur['statut'] ?? '') !== 'ACTIF') {
                        $erreur = 'Compte suspendu.';
                    } else {
                        $hash = (string) ($utilisateur['mot_de_passe_hash'] ?? '');

                        if ($hash === '' || !password_verify($motDePasse, $hash)) {
                            $erreur = 'Identifiants invalides.';
                        } else {
                            $idUtilisateur = (int) ($utilisateur['id_utilisateur'] ?? 0);
                            $pseudo = (string) ($utilisateur['pseudo'] ?? '');

                            $roleChauffeur = (bool) ($utilisateur['role_chauffeur'] ?? false);
                            $rolePassager = (bool) ($utilisateur['role_passager'] ?? false);

                            // Normalisation EXISTS -> bool
                            $estEmployeBrut = $utilisateur['est_employe'] ?? false;
                            $estAdministrateurBrut = $utilisateur['est_administrateur'] ?? false;

                            $estEmploye =
                                $estEmployeBrut === true
                                || $estEmployeBrut === 1
                                || $estEmployeBrut === '1'
                                || $estEmployeBrut === 't';

                            $estAdministrateur =
                                $estAdministrateurBrut === true
                                || $estAdministrateurBrut === 1
                                || $estAdministrateurBrut === '1'
                                || $estAdministrateurBrut === 't';

                            if ($idUtilisateur <= 0 || trim($pseudo) === '') {
                                $erreur = 'Erreur lors de la connexion.';
                            } else {
                                // Création de la session utilisateur (source de vérité)
                                $sessionUtilisateur->connecter(
                                    $idUtilisateur,
                                    $pseudo,
                                    $roleChauffeur,
                                    $rolePassager,
                                    $estEmploye,
                                    $estAdministrateur
                                );

                                // Redirection selon le profil (règle EcoRide)
                                if ($estAdministrateur) {
                                    return $this->redirectToRoute('espace_administrateur');
                                }

                                if ($estEmploye) {
                                    return $this->redirectToRoute('espace_employe');
                                }

                                return $this->redirectToRoute('tableau_de_bord');
                            }
                        }
                    }
                } catch (PDOException) {
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