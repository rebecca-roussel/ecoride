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

      1) Afficher la page de connexion (GET)
         - si déjà connecté : rediriger vers le tableau de bord
         - sinon : afficher le formulaire

      2) Traiter le formulaire (POST)
         a) lire et valider les champs (email / mot de passe)
         b) chercher l’utilisateur en base par email
         c) vérifier : existe ? statut ACTIF ? mot de passe ok ?
         d) calculer les rôles (chauffeur / passager / employé / administrateur)
         e) ouvrir la session via SessionUtilisateur->connecter(...)
         f) rediriger vers le tableau de bord

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
        /*
          1) Garde-fou : si déjà connecté, on ne montre pas la page de connexion
             (ça évite de "reconnecter" quelqu’un qui a déjà une session)
        */
        if ($sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('tableau_de_bord');
        }

        // Variables d'affichage (réutilisées dans Twig)
        $erreur = null;
        $emailSaisi = '';

        /*
          2) Si on reçoit le formulaire (POST), on essaie de connecter l’utilisateur
             Sinon (GET) : on affiche juste la page.
        */
        if ($request->isMethod('POST')) {
            // Lecture des champs (on nettoie l’email, le mot de passe reste tel quel)
            $emailSaisi = trim((string) $request->request->get('email', ''));
            $motDePasse = (string) $request->request->get('mot_de_passe', '');

            // Validation minimale
            if ($emailSaisi === '' || $motDePasse === '') {
                $erreur = 'Email et mot de passe sont obligatoires.';
            } else {
                try {
                    /*
                      2.b) Connexion à la base via notre service (PDO)
                      - ici on fait une requête préparée pour éviter les injections SQL
                    */
                    $pdo = $connexion->obtenirPdo();

                    /*
                      2.c) On récupère l’utilisateur par email + ses infos utiles
                      - + 2 colonnes booléennes pour savoir s'il est employé / admin
                        (via EXISTS sur les tables employe / administrateur)
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

                    /*
                      2.d) Contrôles "métier" :
                      - utilisateur trouvé ?
                      - compte actif ?
                      - mot de passe valide ?
                    */
                    if (!is_array($utilisateur)) {
                        $erreur = 'Identifiants invalides.';
                    } elseif (($utilisateur['statut'] ?? '') !== 'ACTIF') {
                        $erreur = 'Compte suspendu.';
                    } else {
                        $hash = (string) ($utilisateur['mot_de_passe_hash'] ?? '');

                        if ($hash === '' || !password_verify($motDePasse, $hash)) {
                            $erreur = 'Identifiants invalides.';
                        } else {
                            /*
                              2.e) Extraction + conversion des champs
                              - on force les types pour éviter les surprises
                            */
                            $idUtilisateur = (int) ($utilisateur['id_utilisateur'] ?? 0);
                            $pseudo = (string) ($utilisateur['pseudo'] ?? '');

                            $roleChauffeur = (bool) ($utilisateur['role_chauffeur'] ?? false);
                            $rolePassager = (bool) ($utilisateur['role_passager'] ?? false);

                            /*
                              PostgreSQL (via PDO) peut renvoyer les booléens en :
                              - true/false
                              - 1/0
                              - 't'/'f'
                              Donc on "normalise" en bool propre.
                            */
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

                            /*
                              2.f) Sécurité : on évite une session incohérente
                              (si l’id ou le pseudo sont invalides, on refuse la connexion)
                            */
                            if ($idUtilisateur <= 0 || trim($pseudo) === '') {
                                $erreur = 'Erreur lors de la connexion.';
                            } else {
                                /*
                                  2.g) Création de la session utilisateur
                                  -> notre service SessionUtilisateur devient la "source de vérité"
                                */
                                $sessionUtilisateur->connecter(
                                    $idUtilisateur,
                                    $pseudo,
                                    $roleChauffeur,
                                    $rolePassager,
                                    $estEmploye,
                                    $estAdministrateur
                                );

                                /*
                                2.h) Connexion OK -> redirection selon le type de compte

                                Règle EcoRide :
                                - administrateur : va directement dans son espace
                                - employé : va directement dans son espace
                                - sinon : tableau de bord (chauffeur / passager)
                                */
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
                    // On reste volontairement vague (sécurité + UX)
                    $erreur = 'Erreur lors de la connexion.';
                }
            }
        }

        /*
          3) Affichage final (GET, ou POST avec erreur)
          - on renvoie l’erreur + l’email saisi pour éviter de le retaper
        */
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
        /*
          Déconnexion :
          - on nettoie la session
          - puis on revient sur l’accueil
        */
        $sessionUtilisateur->deconnecter();

        return $this->redirectToRoute('accueil');
    }
}
