<?php

declare(strict_types=1);

namespace App\Controller;

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
 * Cette classe gère le formulaire de connexion, l'ouverture de session
 * et la redirection selon le profil de l'utilisateur connecté.
 *
 * Le contrôleur garde ici le rôle de Symfony :
 * il lit la requête HTTP, prépare les messages à afficher,
 * appelle les services utiles, puis choisit la réponse à renvoyer.
 *
 * La lecture des données utilisateur dans PostgreSQL
 * sont déléguées à `PersistanceUtilisateurPostgresql`.
 *
 * @package App\Controller
 */
final class ConnexionController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion et traite sa soumission.
     *
     * Cette méthode joue deux rôles selon la méthode HTTP reçue :
     * - en GET, elle affiche la page ;
     * - en POST, elle lit les champs du formulaire, vérifie les identifiants,
     *   ouvre la session puis redirige selon le profil.
     *
     * `PersistanceUtilisateurPostgresql` sert ici à récupérer les données minimales
     * nécessaires à l'authentification.
     *
     * `SessionUtilisateur` centralise la gestion de session :
     * c'est lui qui sait si un utilisateur est déjà connecté,
     * puis qui stocke les informations après authentification.
     *
     * @param Request $request Requête HTTP courante.
     * @param PersistanceUtilisateurPostgresql $persistanceUtilisateur
     *        Service de lecture utilisateur dans PostgreSQL.
     * @param SessionUtilisateur $sessionUtilisateur Service de gestion de session.
     *
     * @return Response Page Twig ou redirection.
     */
    #[Route('/connexion', name: 'connexion', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        /*
         * Si une session existe déjà, on évite d'afficher à nouveau
         * le formulaire de connexion.
         *
         * La redirection dépend du profil déjà stocké en session :
         * administrateur, employé ou utilisateur standard.
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
         * Ces variables servent à l'affichage du formulaire.
         * Elles permettent de réafficher l'e-mail saisi
         * et un éventuel message d'erreur si la tentative échoue.
         */
        $erreur = null;
        $emailSaisi = '';

        if ($request->isMethod('POST')) {
            /*
             * `trim()` supprime les espaces inutiles au début et à la fin.
             * Cela évite de traiter une saisie remplie uniquement avec des blancs.
             */
            $emailSaisi = trim((string) $request->request->get('email', ''));
            $motDePasse = (string) $request->request->get('mot_de_passe', '');

            if ($emailSaisi === '' || $motDePasse === '') {
                $erreur = 'Email et mot de passe sont obligatoires.';
            } else {
                try {
                    /*
                     * La persistance renvoie ici l'utilisateur avec les champs utiles
                     * au parcours de connexion :
                     * identifiant, pseudo, hash du mot de passe, statut,
                     * rôles publics et indicateurs employé / administrateur.
                     */
                    $utilisateur = $persistanceUtilisateur->trouverUtilisateurPourConnexionParEmail($emailSaisi);

                    if (!is_array($utilisateur)) {
                        $erreur = 'Identifiants invalides.';
                    } elseif (($utilisateur['statut'] ?? '') !== 'ACTIF') {
                        $erreur = 'Compte suspendu.';
                    } else {
                        $hash = (string) ($utilisateur['mot_de_passe_hash'] ?? '');

                        /*
                         * `password_verify()` compare le mot de passe saisi
                         * avec le hash enregistré en base.
                         * Le mot de passe en clair n'est donc jamais stocké.
                         */
                        if ($hash === '' || !password_verify($motDePasse, $hash)) {
                            $erreur = 'Identifiants invalides.';
                        } else {
                            $idUtilisateur = (int) ($utilisateur['id_utilisateur'] ?? 0);
                            $pseudo = (string) ($utilisateur['pseudo'] ?? '');

                            /*
                             * Les rôles chauffeur et passager proviennent directement
                             * de la table utilisateur.
                             */
                            $roleChauffeur = (bool) ($utilisateur['role_chauffeur'] ?? false);
                            $rolePassager = (bool) ($utilisateur['role_passager'] ?? false);

                            /*
                             * Les colonnes calculées avec `EXISTS` peuvent revenir
                             * sous plusieurs formes selon le pilote PDO et le type retourné
                             * par PostgreSQL : booléen, entier ou chaîne.
                             *
                             * Cette normalisation ramène ces valeurs
                             * à de vrais booléens PHP.
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

                            if ($idUtilisateur <= 0 || trim($pseudo) === '') {
                                $erreur = 'Erreur lors de la connexion.';
                            } else {
                                /*
                                 * La session devient ici la source de vérité côté application
                                 * pour savoir qui est connecté et avec quels rôles.
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
                                 * La destination dépend du profil stocké en session.
                                 * Un administrateur est prioritaire sur un employé,
                                 * puis l'utilisateur standard rejoint son tableau de bord.
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
                     * En cas d'erreur technique liée à PostgreSQL,
                     * on garde un message neutre côté interface.
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
     * Cette méthode vide les informations stockées en session
     * pour considérer l'utilisateur comme déconnecté.
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
}