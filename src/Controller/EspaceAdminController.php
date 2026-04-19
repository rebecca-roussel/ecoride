<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceAdministrationPostgresql;
use App\Service\SessionUtilisateur;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de l'espace administrateur.
 *
 * Cette classe gère les parcours HTTP réservés à l'administrateur :
 * affichage du tableau de bord,
 * création d'un employé,
 * suspension d'un compte
 * et réactivation d'un compte.
 *
 * Le contrôleur garde ici ce qui relève du web :
 * lecture de la requête,
 * contrôle d'accès,
 * vérification CSRF,
 * validation simple des champs,
 * messages flash
 * et redirections.
 *
 * Les accès à PostgreSQL sont délégués à `PersistanceAdministrationPostgresql`.
 *
 * La journalisation MongoDB est utilisée ici
 * pour tracer une suspension de compte réellement effectuée.
 */
final class EspaceAdminController extends AbstractController
{
    /**
     * Affiche la page principale de l'espace administrateur.
     *
     * La page repose sur deux onglets :
     * - les comptes ;
     * - les statistiques.
     *
     * Les données affichées sont préparées par la persistance dédiée :
     * statistiques globales,
     * liste des comptes,
     * nombre de covoiturages par jour
     * et crédits générés par jour.
     */
    #[Route('/espace-administrateur', name: 'espace_administrateur', methods: ['GET'])]
    public function index(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceAdministrationPostgresql $persistanceAdministration,
    ): Response {
        /*
         * L'accès à cet espace est réservé à un compte connecté
         * disposant du rôle administrateur.
         */
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estAdmin()) {
            return $this->redirectToRoute('connexion');
        }

        /*
         * L'onglet est transmis en paramètre GET.
         * On garde seulement les deux valeurs attendues
         * pour éviter un état d'affichage incohérent.
         */
        $onglet = (string) $request->query->get('onglet', 'comptes');
        $onglet = $onglet === 'statistiques' ? 'statistiques' : 'comptes';

        /*
         * Les lectures SQL sont centralisées dans la persistance.
         * Le contrôleur reçoit ici des données déjà prêtes à afficher.
         */
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

    /**
     * Affiche et traite le formulaire de création d'un employé.
     *
     * En GET, la méthode affiche le formulaire vide.
     * En POST, elle lit les champs,
     * applique les validations simples,
     * sécurise le mot de passe
     * puis délègue la création du compte à PostgreSQL.
     *
     * Le compte créé ici est un compte interne.
     * Il est donc enregistré avec `role_interne = true`
     * puis relié à la table `employe`.
     */
    #[Route('/espace-administrateur/creer-employe', name: 'espace_administrateur_creer_employe', methods: ['GET', 'POST'])]
    public function creerEmploye(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceAdministrationPostgresql $persistanceAdministration,
    ): Response {
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estAdmin()) {
            return $this->redirectToRoute('connexion');
        }

        /*
         * Ces valeurs servent à réafficher le formulaire
         * avec la saisie conservée si une erreur survient.
         */
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

            /*
             * Le jeton CSRF protège l'action contre une soumission frauduleuse.
             *
             * CSRF signifie Cross-Site Request Forgery.
             * C'est un contrôle qui vérifie
             * que le formulaire vient bien de l'application.
             */
            $jeton = (string) $request->request->get('_csrf', '');
            if (!$this->isCsrfTokenValid('creer_employe', $jeton)) {
                $erreurs[] = 'Jeton de sécurité invalide. Veuillez réessayer.';
            }

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

            /*
             * `password_hash()` produit le hash sécurisé
             * qui sera stocké en base.
             * Le mot de passe en clair n'est donc jamais enregistré.
             */
            $hash = null;
            if (count($erreurs) === 0) {
                $hashGenere = password_hash($motDePasse, PASSWORD_BCRYPT);

                if (!is_string($hashGenere) || $hashGenere === '') {
                    $erreurs[] = 'Impossible de sécuriser le mot de passe.';
                } else {
                    $hash = $hashGenere;
                }
            }

            if (count($erreurs) === 0 && $hash !== null) {
                try {
                    $persistanceAdministration->creerEmploye(
                        $valeurs['pseudo'],
                        $valeurs['email'],
                        $hash
                    );

                    $this->addFlash('succes', 'Compte employé créé avec succès.');

                    return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
                } catch (Throwable) {
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

    /**
     * Suspend un compte utilisateur depuis l'espace administrateur.
     *
     * Le contrôleur vérifie ici :
     * l'accès administrateur,
     * le jeton CSRF,
     * l'identifiant du compte ciblé
     * et l'interdiction de se suspendre soi-même.
     *
     * La mise à jour réelle en base est déléguée à PostgreSQL.
     *
     * Quand la suspension touche réellement un compte,
     * l'événement MongoDB `compte_suspendu` est enregistré.
     */
    #[Route('/espace-administrateur/suspendre-compte', name: 'espace_admin_suspendre_compte', methods: ['POST'])]
    public function suspendreCompte(
        Request $request,
        PersistanceAdministrationPostgresql $persistanceAdministration,
        SessionUtilisateur $sessionUtilisateur,
        JournalEvenements $journalEvenements,
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

        /*
         * Cette garde évite à l'administrateur
         * de bloquer son propre accès à l'application.
         */
        $idAdministrateur = (int) ($sessionUtilisateur->idUtilisateur() ?? 0);
        if ($idAdministrateur > 0 && $idCible === $idAdministrateur) {
            $this->addFlash('erreur', 'Vous ne pouvez pas suspendre votre propre compte.');

            return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
        }

        try {
            /*
             * La persistance renvoie le pseudo du compte suspendu.
             * Si elle renvoie `null`, aucune ligne n'a été modifiée.
             */
            $pseudoCible = $persistanceAdministration->suspendreCompteParId($idCible);

            if ($pseudoCible === null) {
                $this->addFlash('erreur', 'Aucun compte trouvé : suspension non effectuée.');
            } else {
                $journalEvenements->enregistrer(
                    'compte_suspendu',
                    'utilisateur',
                    $idCible,
                    [
                        'id_administrateur' => $idAdministrateur,
                        'pseudo_cible' => $pseudoCible,
                    ]
                );

                $libelle = $pseudoCible !== ''
                    ? sprintf('Le compte "%s" a été suspendu.', $pseudoCible)
                    : 'Le compte a été suspendu.';

                $this->addFlash('avertissement', $libelle);
            }
        } catch (Throwable) {
            $this->addFlash('erreur', 'Erreur lors de la suspension du compte.');
        }

        return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
    }

    /**
     * Réactive un compte utilisateur depuis l'espace administrateur.
     *
     * Le contrôleur garde ici le parcours HTTP :
     * contrôle d'accès,
     * vérification CSRF,
     * messages d'interface
     * et redirection.
     *
     * La mise à jour réelle en base est déléguée à PostgreSQL.
     */
    #[Route('/espace-administrateur/reactiver-compte', name: 'espace_admin_reactiver_compte', methods: ['POST'])]
    public function reactiverCompte(
        Request $request,
        PersistanceAdministrationPostgresql $persistanceAdministration,
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
            $pseudoCible = $persistanceAdministration->reactiverCompteParId($idCible);

            if ($pseudoCible === null) {
                $this->addFlash('erreur', 'Aucun compte trouvé : réactivation non effectuée.');
            } else {
                $libelle = $pseudoCible !== ''
                    ? sprintf('Le compte "%s" a été réactivé.', $pseudoCible)
                    : 'Le compte a été réactivé.';

                $this->addFlash('succes', $libelle);
            }
        } catch (Throwable) {
            $this->addFlash('erreur', 'Erreur lors de la réactivation du compte.');
        }

        return $this->redirectToRoute('espace_administrateur', ['onglet' => 'comptes']);
    }
}