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
    #[Route('/connexion', name: 'connexion', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ConnexionPostgresql $connexion,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        /*
          PLAN (ConnexionController) :

          1) Si déjà connecté :
             - redirection selon le profil (administrateur / employé / utilisateur)

          2) POST :
             - lire email + mot de passe
             - charger l'utilisateur (BDD) + déterminer admin/employé via tables dédiées
             - vérifier statut + mot de passe
             - créer la session
             - redirection selon le profil
        */

        // 1) Déjà connecté : on évite la page de connexion
        if ($sessionUtilisateur->estConnecte()) {
            if ($sessionUtilisateur->estAdmin()) {
                return $this->redirectToRoute('espace_administrateur');
            }

            if ($sessionUtilisateur->estEmploye()) {
                return $this->redirectToRoute('tableau_de_bord');
            }

            return $this->redirectToRoute('tableau_de_bord');
        }

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
                      On récupère :
                      - l'utilisateur (u.* utile à la connexion)
                      - + deux drapeaux calculés :
                        * est_admin  : existe dans table administrateur
                        * est_employe: existe dans table employe

                      ⚠️ PostgreSQL renvoie souvent 't'/'f' pour EXISTS,
                      donc on gèrera les deux formes côté PHP.
                    */
                    $stmt = $pdo->prepare('
                        SELECT
                            u.id_utilisateur,
                            u.pseudo,
                            u.email,
                            u.mot_de_passe_hash,
                            u.statut,
                            u.role_chauffeur,
                            u.role_passager,
                            u.role_interne,
                            EXISTS (SELECT 1 FROM administrateur a WHERE a.id_utilisateur = u.id_utilisateur) AS est_admin,
                            EXISTS (SELECT 1 FROM employe e       WHERE e.id_utilisateur = u.id_utilisateur) AS est_employe
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

                            // EXISTS -> 't'/'f' ou bool selon driver/config
                            $estAdmin = (($utilisateur['est_admin'] ?? false) === true) || (($utilisateur['est_admin'] ?? '') === 't');
                            $estEmploye = (($utilisateur['est_employe'] ?? false) === true) || (($utilisateur['est_employe'] ?? '') === 't');

                            // Sécurité : session cohérente
                            if ($idUtilisateur <= 0 || trim($pseudo) === '') {
                                $erreur = 'Erreur lors de la connexion.';
                            } else {
                                $sessionUtilisateur->connecter(
                                    $idUtilisateur,
                                    $pseudo,
                                    $roleChauffeur,
                                    $rolePassager,
                                    $estAdmin,
                                    $estEmploye
                                );

                                // Redirection selon le profil
                                if ($estAdmin) {
                                    return $this->redirectToRoute('espace_administrateur');
                                }

                                if ($estEmploye) {
                                    return $this->redirectToRoute('tableau_de_bord');
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
