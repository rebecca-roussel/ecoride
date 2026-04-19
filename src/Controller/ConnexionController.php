<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceUtilisateurPostgresql;
use App\Service\SessionUtilisateur;
use PDOException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du parcours de connexion utilisateur.
 *
 * Cette classe gère le formulaire de connexion,
 * l'ouverture de session
 * et la redirection vers la bonne zone de l'application
 * selon le profil de l'utilisateur connecté.
 *
 * Le contrôleur garde ici la logique HTTP :
 * il lit la requête,
 * récupère les champs du formulaire,
 * prépare les messages à afficher,
 * appelle les services utiles
 * puis décide de la réponse à renvoyer.
 *
 * Les données utilisateur sont lues dans PostgreSQL
 * par `PersistanceUtilisateurPostgresql`.
 *
 * La session PHP est pilotée par `SessionUtilisateur`.
 * C'est ce service qui stocke ensuite les informations
 * de l'utilisateur authentifié.
 *
 * La journalisation MongoDB passe par `JournalEvenements`
 * pour tracer les connexions réussies
 * et certaines connexions échouées.
 */
final class ConnexionController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion et traite sa soumission.
     *
     * Cette méthode gère deux cas :
     * - en GET, elle affiche la page ;
     * - en POST, elle vérifie les identifiants,
     *   ouvre la session si tout est correct,
     *   puis redirige vers la bonne page.
     *
     * Le service de persistance renvoie ici uniquement
     * les données utiles à l'authentification :
     * identifiant, pseudo, hash du mot de passe,
     * statut du compte
     * et rôles utiles à la redirection.
     *
     * @param Request $request Requête HTTP courante.
     * @param PersistanceUtilisateurPostgresql $persistanceUtilisateur Service de lecture PostgreSQL.
     * @param SessionUtilisateur $sessionUtilisateur Service de gestion de session.
     * @param JournalEvenements $journalEvenements Service de journalisation MongoDB.
     *
     * @return Response Page Twig ou redirection.
     */
    #[Route('/connexion', name: 'connexion', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur,
        SessionUtilisateur $sessionUtilisateur,
        JournalEvenements $journalEvenements,
    ): Response {
        /*
         * Si une session existe déjà,
         * on ne réaffiche pas le formulaire.
         *
         * La redirection dépend du profil déjà présent en session.
         */
        if ($sessionUtilisateur->estConnecte()) {
            if ($sessionUtilisateur->estAdmin()) {
                return $this->redirectToRoute('espace_administrateur');
            }

            if ($sessionUtilisateur->estEmploye()) {
                return $this->redirectToRoute('espace_employe');
            }

            return $this->redirectToRoute('tableau_de_bord');
        }

        /*
         * Ces variables servent à réafficher le formulaire.
         * On garde l'e-mail saisi
         * et un éventuel message d'erreur.
         */
        $erreur = null;
        $emailSaisi = '';

        if ($request->isMethod('POST')) {
            /*
             * `trim()` retire les espaces inutiles
             * au début et à la fin d'une chaîne.
             * Cela évite de considérer comme valide
             * une saisie remplie seulement avec des blancs.
             */
            $emailSaisi = trim((string) $request->request->get('email', ''));
            $motDePasse = (string) $request->request->get('mot_de_passe', '');

            if ($emailSaisi === '' || $motDePasse === '') {
                $erreur = 'Email et mot de passe sont obligatoires.';
            } else {
                try {
                    /*
                     * On charge ici l'utilisateur lié à l'e-mail saisi.
                     * Si aucun compte ne correspond, la persistance renvoie `null`.
                     */
                    $utilisateur = $persistanceUtilisateur->trouverUtilisateurPourConnexionParEmail($emailSaisi);

                    if (!is_array($utilisateur)) {
                        $erreur = 'Identifiants invalides.';
                    } elseif (($utilisateur['statut'] ?? '') !== 'ACTIF') {
                        /*
                         * Un compte suspendu existe bien,
                         * mais il n'a pas le droit d'ouvrir une session.
                         */
                        $erreur = 'Compte suspendu.';

                        $idUtilisateur = (int) ($utilisateur['id_utilisateur'] ?? 0);
                        if ($idUtilisateur > 0) {
                            $this->journaliserConnexionEchouee(
                                $journalEvenements,
                                $idUtilisateur,
                                'compte_suspendu',
                                $emailSaisi
                            );
                        }
                    } else {
                        $hash = (string) ($utilisateur['mot_de_passe_hash'] ?? '');

                        /*
                         * `password_verify()` compare le mot de passe saisi
                         * avec le hash stocké en base.
                         *
                         * Un hash est une version transformée et sécurisée
                         * du mot de passe.
                         * L'application ne stocke donc pas
                         * le mot de passe en clair.
                         */
                        if ($hash === '' || !password_verify($motDePasse, $hash)) {
                            $erreur = 'Identifiants invalides.';

                            $idUtilisateur = (int) ($utilisateur['id_utilisateur'] ?? 0);
                            if ($idUtilisateur > 0) {
                                $this->journaliserConnexionEchouee(
                                    $journalEvenements,
                                    $idUtilisateur,
                                    'mot_de_passe_invalide',
                                    $emailSaisi
                                );
                            }
                        } else {
                            $idUtilisateur = (int) ($utilisateur['id_utilisateur'] ?? 0);
                            $pseudo = (string) ($utilisateur['pseudo'] ?? '');

                            /*
                             * Les rôles chauffeur et passager
                             * viennent directement de la table utilisateur.
                             */
                            $roleChauffeur = (bool) ($utilisateur['role_chauffeur'] ?? false);
                            $rolePassager = (bool) ($utilisateur['role_passager'] ?? false);

                            /*
                             * Les indicateurs `est_employe` et `est_administrateur`
                             * sont calculés dans SQL avec `EXISTS`.
                             *
                             * `EXISTS` est un test SQL qui répond à une question simple :
                             * est-ce qu'une ligne correspondante existe ?
                             *
                             * Selon le pilote PDO et le type de retour PostgreSQL,
                             * ces valeurs peuvent arriver sous plusieurs formes :
                             * booléen, entier ou chaîne.
                             *
                             * On les normalise donc ici
                             * pour obtenir de vrais booléens PHP.
                             */
                            $estEmploye = $this->normaliserBooleenPostgresql($utilisateur['est_employe'] ?? false);
                            $estAdministrateur = $this->normaliserBooleenPostgresql($utilisateur['est_administrateur'] ?? false);

                            if ($idUtilisateur <= 0 || trim($pseudo) === '') {
                                $erreur = 'Erreur lors de la connexion.';
                            } else {
                                /*
                                 * La session devient ici la source de vérité
                                 * côté application.
                                 * C'est elle qui dira ensuite
                                 * qui est connecté
                                 * et avec quels rôles.
                                 */
                                $sessionUtilisateur->connecter(
                                    $idUtilisateur,
                                    $pseudo,
                                    $roleChauffeur,
                                    $rolePassager,
                                    $estEmploye,
                                    $estAdministrateur
                                );

                                $journalEvenements->enregistrer(
                                    'connexion_reussie',
                                    'utilisateur',
                                    $idUtilisateur,
                                    [
                                        'email' => $emailSaisi,
                                        'est_employe' => $estEmploye,
                                        'est_administrateur' => $estAdministrateur,
                                    ]
                                );

                                /*
                                 * La destination dépend du profil connecté.
                                 * L'administrateur est prioritaire,
                                 * puis l'employé,
                                 * puis l'utilisateur standard.
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
                    /*
                     * Si PostgreSQL remonte une erreur technique,
                     * on garde un message neutre côté interface.
                     *
                     * Ici, on ne journalise pas `connexion_echouee`,
                     * car on n'a pas forcément un identifiant utilisateur exploitable.
                     */
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

    /**
     * Ferme la session utilisateur puis renvoie vers l'accueil.
     *
     * Cette méthode vide les informations stockées en session.
     * L'application considère alors l'utilisateur comme déconnecté.
     *
     * @param SessionUtilisateur $sessionUtilisateur Service de gestion de session.
     *
     * @return Response Redirection vers l'accueil.
     */
    #[Route('/deconnexion', name: 'deconnexion', methods: ['POST', 'GET'])]
    public function deconnexion(SessionUtilisateur $sessionUtilisateur): Response
    {
        $sessionUtilisateur->deconnecter();

        return $this->redirectToRoute('accueil');
    }

    /**
     * Convertit une valeur PostgreSQL en vrai booléen PHP.
     *
     * PostgreSQL peut renvoyer un booléen
     * sous plusieurs formes selon le contexte :
     * `true`,
     * `false`,
     * `1`,
     * `0`,
     * `'1'`,
     * `'0'`,
     * `'t'`
     * ou `'f'`.
     *
     * Cette méthode uniformise cette lecture
     * pour éviter des tests ambigus dans le contrôleur.
     *
     * @param mixed $valeur Valeur brute renvoyée par PostgreSQL.
     *
     * @return bool Booléen PHP normalisé.
     */
    private function normaliserBooleenPostgresql(mixed $valeur): bool
    {
        return $valeur === true
            || $valeur === 1
            || $valeur === '1'
            || $valeur === 't';
    }

    /**
     * Journalise une connexion échouée
     * lorsqu'un identifiant utilisateur exploitable est connu.
     *
     * Cette méthode garde un format stable dans MongoDB :
     * on enregistre l'événement,
     * l'utilisateur concerné
     * et la raison principale de l'échec.
     *
     * @param JournalEvenements $journalEvenements Service de journalisation MongoDB.
     * @param int $idUtilisateur Identifiant utilisateur connu.
     * @param string $raison Motif principal de l'échec.
     * @param string $emailSaisi E-mail saisi dans le formulaire.
     *
     * @return void
     */
    private function journaliserConnexionEchouee(
        JournalEvenements $journalEvenements,
        int $idUtilisateur,
        string $raison,
        string $emailSaisi
    ): void {
        $journalEvenements->enregistrer(
            'connexion_echouee',
            'utilisateur',
            $idUtilisateur,
            [
                'raison' => $raison,
                'email' => $emailSaisi,
            ]
        );
    }
}