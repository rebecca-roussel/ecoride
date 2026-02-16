<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConnexionPostgresql;
use App\Service\PersistanceAdministrationPostgresql;
use App\Service\SessionUtilisateur;
use PDO;
use PDOException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EspaceAdminController extends AbstractController
{
    #[Route('/espace-administrateur', name: 'espace_administrateur', methods: ['GET'])]
    public function index(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceAdministrationPostgresql $persistanceAdministration,
    ): Response {
        // Sécurité : espace admin uniquement
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estAdmin()) {
            return $this->redirectToRoute('connexion');
        }

        // Onglet (sans JS)
        $onglet = (string) $request->query->get('onglet', 'comptes');
        $onglet = $onglet === 'statistiques' ? 'statistiques' : 'comptes';

        // Données BDD
        $stats = $persistanceAdministration->obtenirStats();
        $comptes = $persistanceAdministration->listerComptes(50);
        $covoituragesParJour = $persistanceAdministration->obtenirCovoituragesParJour(14);
        $creditsParJour = $persistanceAdministration->obtenirCreditsParJour(14);


        return $this->render('espace_administrateur/index.html.twig', [
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
            'onglet' => $onglet,
            'stats' => $stats,
            'comptes' => $comptes,
            'covoiturages_par_jour' => $covoituragesParJour,
            'credits_par_jour' => $creditsParJour,
            'lien_creation_employe' => $this->generateUrl('espace_administrateur_creer_employe'),
        ]);
    }

    #[Route('/espace-administrateur/creer-employe', name: 'espace_administrateur_creer_employe', methods: ['GET', 'POST'])]
    public function creerEmploye(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        ConnexionPostgresql $connexion,
    ): Response {
        // Sécurité : espace admin uniquement
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estAdmin()) {
            return $this->redirectToRoute('connexion');
        }

        $erreurs = [];
        $valeurs = [
            'pseudo' => '',
            'email' => '',
        ];

        if ($request->isMethod('POST')) {
            $valeurs['pseudo'] = trim((string) $request->request->get('pseudo', ''));
            $valeurs['email'] = trim((string) $request->request->get('email', ''));
            $motDePasse = (string) $request->request->get('mot_de_passe', '');
            $confirmation = (string) $request->request->get('confirmation', '');

            // CSRF
            $jeton = (string) $request->request->get('_csrf', '');
            if (!$this->isCsrfTokenValid('creer_employe', $jeton)) {
                $erreurs[] = 'Jeton de sécurité invalide. Veuillez réessayer.';
            }

            // Validations
            if ($valeurs['pseudo'] === '') {
                $erreurs[] = 'Le pseudo est obligatoire.';
            }

            if ($valeurs['email'] === '' || !filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
                $erreurs[] = 'Email invalide.';
            }

            if ($motDePasse === '' || mb_strlen($motDePasse) < 8) {
                $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }

            if ($motDePasse !== $confirmation) {
                $erreurs[] = 'La confirmation du mot de passe ne correspond pas.';
            }

            if (count($erreurs) === 0) {
                $pdo = null;

                try {
                    $pdo = $connexion->obtenirPdo();
                    $pdo->beginTransaction();

                    // 1) Créer l’utilisateur (role_interne=true pour respecter la contrainte)
                    $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

                    $stmt = $pdo->prepare("
                        INSERT INTO utilisateur (
                            pseudo, email, mot_de_passe_hash,
                            credits, role_chauffeur, role_passager, role_interne, statut
                        )
                        VALUES (
                            :pseudo, :email, :hash,
                            0, false, false, true, 'ACTIF'
                        )
                        RETURNING id_utilisateur
                    ");
                    $stmt->execute([
                        'pseudo' => $valeurs['pseudo'],
                        'email' => $valeurs['email'],
                        'hash' => $hash,
                    ]);

                    $idUtilisateur = (int) $stmt->fetchColumn();

                    if ($idUtilisateur <= 0) {
                        throw new PDOException('Création utilisateur impossible.');
                    }

                    // 2) Le marquer employé
                    $stmt2 = $pdo->prepare("INSERT INTO employe (id_utilisateur) VALUES (:id)");
                    $stmt2->execute(['id' => $idUtilisateur]);

                    $pdo->commit();

                    $this->addFlash('succes', 'Compte employé créé avec succès.');

                    return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
                } catch (PDOException) {
                    if ($pdo !== null) {
                        try {
                            $pdo->rollBack();
                        } catch (\Throwable) {
                        }
                    }

                    $erreurs[] = 'Impossible de créer l’employé (pseudo ou email déjà utilisé, ou erreur BDD).';
                }
            }
        }

        return $this->render('espace_administrateur/creer_employe.html.twig', [
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
            'erreurs' => $erreurs,
            'valeurs' => $valeurs,
        ]);
    }

    #[Route('/espace-administrateur/suspendre-compte', name: 'espace_admin_suspendre_compte', methods: ['POST'])]
    public function suspendreCompte(
        Request $request,
        ConnexionPostgresql $connexion,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estAdmin()) {
            throw $this->createAccessDeniedException('Accès réservé administrateur.');
        }

        $idCible = (int) $request->request->get('id_utilisateur', 0);
        $token = (string) $request->request->get('_token', '');

        if ($idCible <= 0 || !$this->isCsrfTokenValid('suspendre_compte_' . $idCible, $token)) {
            $this->addFlash('erreur', 'Action refusée : jeton de sécurité invalide.');
            return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
        }

        // Sécurité : ne pas pouvoir se suspendre soi-même
        $idConnecte = $sessionUtilisateur->idUtilisateur() ?? 0;
        if ($idConnecte > 0 && $idCible === $idConnecte) {
            $this->addFlash('erreur', 'Vous ne pouvez pas suspendre votre propre compte.');
            return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
        }

        try {
            $pdo = $connexion->obtenirPdo();

            // (Optionnel mais utile) récupérer le pseudo pour un message plus clair
            $stmtPseudo = $pdo->prepare("SELECT pseudo FROM utilisateur WHERE id_utilisateur = :id");
            $stmtPseudo->execute(['id' => $idCible]);
            $pseudoCible = (string) ($stmtPseudo->fetchColumn() ?: '');

            $stmt = $pdo->prepare("
                UPDATE utilisateur
                SET statut = 'SUSPENDU',
                    date_changement_statut = NOW()
                WHERE id_utilisateur = :id
            ");
            $stmt->execute(['id' => $idCible]);

            if ($stmt->rowCount() === 0) {
                $this->addFlash('erreur', 'Aucun compte trouvé : suspension non effectuée.');
            } else {
                $libelle = $pseudoCible !== '' ? sprintf('Le compte "%s" a été suspendu.', $pseudoCible) : 'Le compte a été suspendu.';
                $this->addFlash('avertissement', $libelle);
            }
        } catch (PDOException) {
            $this->addFlash('erreur', 'Erreur lors de la suspension du compte.');
        }

        return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
    }

    #[Route('/espace-administrateur/reactiver-compte', name: 'espace_admin_reactiver_compte', methods: ['POST'])]
    public function reactiverCompte(
        Request $request,
        ConnexionPostgresql $connexion,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estAdmin()) {
            throw $this->createAccessDeniedException('Accès réservé administrateur.');
        }

        $idCible = (int) $request->request->get('id_utilisateur', 0);
        $token = (string) $request->request->get('_token', '');

        if ($idCible <= 0 || !$this->isCsrfTokenValid('reactiver_compte_' . $idCible, $token)) {
            $this->addFlash('erreur', 'Action refusée : jeton de sécurité invalide.');
            return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
        }

        try {
            $pdo = $connexion->obtenirPdo();

            // (Optionnel mais utile) récupérer le pseudo pour un message plus clair
            $stmtPseudo = $pdo->prepare("SELECT pseudo FROM utilisateur WHERE id_utilisateur = :id");
            $stmtPseudo->execute(['id' => $idCible]);
            $pseudoCible = (string) ($stmtPseudo->fetchColumn() ?: '');

            $stmt = $pdo->prepare("
                UPDATE utilisateur
                SET statut = 'ACTIF',
                    date_changement_statut = NOW()
                WHERE id_utilisateur = :id
            ");
            $stmt->execute(['id' => $idCible]);

            if ($stmt->rowCount() === 0) {
                $this->addFlash('erreur', 'Aucun compte trouvé : réactivation non effectuée.');
            } else {
                $libelle = $pseudoCible !== '' ? sprintf('Le compte "%s" a été réactivé.', $pseudoCible) : 'Le compte a été réactivé.';
                $this->addFlash('succes', $libelle);
            }
        } catch (PDOException) {
            $this->addFlash('erreur', 'Erreur lors de la réactivation du compte.');
        }

        return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
    }
}
