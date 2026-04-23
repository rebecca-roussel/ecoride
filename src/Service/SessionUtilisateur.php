<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Service de lecture et d'écriture de la session utilisateur.
 *
 * Cette classe centralise les accès aux informations
 * stockées dans la session Symfony pour l'utilisateur connecté.
 *
 * Son rôle consiste à :
 * - retrouver la session courante ;
 * - lire l'identifiant et le pseudo de l'utilisateur ;
 * - savoir si un rôle interne est actif ;
 * - enregistrer ou supprimer les informations de connexion ;
 * - fournir une structure homogène au reste de l'application.
 *
 * Le but est d'éviter de manipuler directement les clés de session
 * dans plusieurs contrôleurs ou services.
 */
final class SessionUtilisateur
{
    /**
     * Clé de session pour l'identifiant utilisateur.
     */
    private const CLE_ID = 'utilisateur_id';

    /**
     * Clé de session pour le pseudo utilisateur.
     */
    private const CLE_PSEUDO = 'utilisateur_pseudo';

    /**
     * Clé de session pour le rôle chauffeur.
     */
    private const CLE_ROLE_CHAUFFEUR = 'utilisateur_role_chauffeur';

    /**
     * Clé de session pour le rôle passager.
     */
    private const CLE_ROLE_PASSAGER = 'utilisateur_role_passager';

    /**
     * Clé de session indiquant si l'utilisateur est employé.
     */
    private const CLE_EST_EMPLOYE = 'utilisateur_est_employe';

    /**
     * Clé de session indiquant si l'utilisateur est administrateur.
     */
    private const CLE_EST_ADMINISTRATEUR = 'utilisateur_est_administrateur';

    /**
     * Initialise le service avec le RequestStack Symfony.
     *
     * RequestStack permet d'accéder à la requête HTTP courante,
     * puis à la session si elle existe.
     *
     * @param RequestStack $requestStack Service Symfony donnant accès à la requête courante.
     */
    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * Retourne la session courante si elle existe.
     *
     * La méthode passe d'abord par la requête HTTP courante.
     * Si aucune requête n'est disponible,
     * ou si aucune session n'est attachée à cette requête,
     * elle renvoie null.
     *
     * Cela évite d'échouer brutalement dans des contextes
     * où aucune session n'est accessible.
     *
     * @return SessionInterface|null Session courante ou null.
     */
    private function session(): ?SessionInterface
    {
        /*
         * On récupère la requête HTTP courante.
         */
        $requete = $this->requestStack->getCurrentRequest();

        /*
         * Si aucune requête n'est disponible,
         * on ne peut pas accéder à une session.
         */
        if ($requete === null) {
            return null;
        }

        /*
         * On renvoie la session seulement si la requête en possède une.
         */
        return $requete->hasSession() ? $requete->getSession() : null;
    }

    /**
     * Indique si un utilisateur est actuellement connecté.
     *
     * La connexion est considérée active
     * si un identifiant utilisateur valide est présent en session.
     *
     * @return bool True si un utilisateur est connecté, sinon false.
     */
    public function estConnecte(): bool
    {
        return $this->idUtilisateur() !== null;
    }

    /**
     * Retourne l'identifiant de l'utilisateur connecté.
     *
     * La méthode lit la valeur stockée en session
     * puis vérifie qu'elle correspond bien à un identifiant exploitable.
     *
     * Les cas acceptés sont :
     * - un entier strictement positif ;
     * - une chaîne composée uniquement de chiffres,
     *   convertissable en entier strictement positif.
     *
     * @return int|null Identifiant utilisateur ou null si la valeur n'est pas exploitable.
     */
    public function idUtilisateur(): ?int
    {
        /*
         * On récupère la session courante.
         */
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        /*
         * On lit la valeur brute de l'identifiant.
         */
        $id = $session->get(self::CLE_ID);

        /*
         * Cas normal :
         * l'identifiant est déjà stocké comme entier.
         */
        if (is_int($id) && $id > 0) {
            return $id;
        }

        /*
         * Cas toléré :
         * l'identifiant est stocké sous forme de chaîne numérique.
         * On le convertit alors en entier.
         */
        if (is_string($id) && ctype_digit($id)) {
            $idInt = (int) $id;

            return $idInt > 0 ? $idInt : null;
        }

        /*
         * Toute autre valeur est considérée comme invalide.
         */
        return null;
    }

    /**
     * Retourne le pseudo de l'utilisateur connecté.
     *
     * La méthode vérifie que la valeur en session
     * est bien une chaîne non vide après nettoyage.
     *
     * @return string|null Pseudo nettoyé ou null si la valeur n'est pas exploitable.
     */
    public function pseudo(): ?string
    {
        /*
         * On récupère la session courante.
         */
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        /*
         * On lit la valeur brute du pseudo.
         */
        $pseudo = $session->get(self::CLE_PSEUDO);

        /*
         * Si ce n'est pas une chaîne, on considère la valeur invalide.
         */
        if (!is_string($pseudo)) {
            return null;
        }

        /*
         * On nettoie le pseudo pour éviter une chaîne vide
         * composée seulement d'espaces.
         */
        $pseudoNettoye = trim($pseudo);

        return $pseudoNettoye !== '' ? $pseudoNettoye : null;
    }

    /**
     * Indique si l'utilisateur connecté possède le rôle employé.
     *
     * @return bool True si le rôle employé est actif, sinon false.
     */
    public function estEmploye(): bool
    {
        /*
         * On récupère la session courante.
         */
        $session = $this->session();
        if ($session === null) {
            return false;
        }

        /*
         * On lit la valeur en session,
         * avec false comme valeur par défaut.
         */
        return (bool) $session->get(self::CLE_EST_EMPLOYE, false);
    }

    /**
     * Indique si l'utilisateur connecté possède le rôle administrateur.
     *
     * @return bool True si le rôle administrateur est actif, sinon false.
     */
    public function estAdmin(): bool
    {
        /*
         * On récupère la session courante.
         */
        $session = $this->session();
        if ($session === null) {
            return false;
        }

        /*
         * On lit la valeur en session,
         * avec false comme valeur par défaut.
         */
        return (bool) $session->get(self::CLE_EST_ADMINISTRATEUR, false);
    }

    /**
     * Alias plus explicite de estAdmin().
     *
     * Cette méthode évite de casser
     * des usages qui emploient le mot complet "administrateur".
     *
     * @return bool True si le rôle administrateur est actif, sinon false.
     */
    public function estAdministrateur(): bool
    {
        return $this->estAdmin();
    }

    /**
     * Enregistre l'utilisateur connecté dans la session.
     *
     * La méthode stocke :
     * - l'identifiant ;
     * - le pseudo ;
     * - les rôles chauffeur et passager ;
     * - les rôles internes employé et administrateur.
     *
     * Deux garde-fous sont appliqués :
     * - l'identifiant doit être strictement positif ;
     * - le pseudo doit rester non vide après nettoyage.
     *
     * Si l'un de ces contrôles échoue,
     * la méthode s'arrête sans rien écrire en session.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur connecté.
     * @param string $pseudo Pseudo de l'utilisateur connecté.
     * @param bool $roleChauffeur Indique si le rôle chauffeur est actif.
     * @param bool $rolePassager Indique si le rôle passager est actif.
     * @param bool $estEmploye Indique si l'utilisateur est employé.
     * @param bool $estAdministrateur Indique si l'utilisateur est administrateur.
     */
    public function connecter(
        int $idUtilisateur,
        string $pseudo,
        bool $roleChauffeur = false,
        bool $rolePassager = false,
        bool $estEmploye = false,
        bool $estAdministrateur = false
    ): void {
        /*
         * On récupère la session courante.
         */
        $session = $this->session();
        if ($session === null) {
            return;
        }

        /*
         * On nettoie le pseudo
         * avant de le stocker.
         */
        $pseudoNettoye = trim($pseudo);

        /*
         * Garde-fous sur les données minimales de connexion.
         */
        if ($idUtilisateur <= 0 || $pseudoNettoye === '') {
            return;
        }

        /*
         * Écriture des informations principales en session.
         */
        $session->set(self::CLE_ID, $idUtilisateur);
        $session->set(self::CLE_PSEUDO, $pseudoNettoye);

        /*
         * Écriture des rôles métier.
         */
        $session->set(self::CLE_ROLE_CHAUFFEUR, $roleChauffeur);
        $session->set(self::CLE_ROLE_PASSAGER, $rolePassager);

        /*
         * Écriture des rôles internes.
         */
        $session->set(self::CLE_EST_EMPLOYE, $estEmploye);
        $session->set(self::CLE_EST_ADMINISTRATEUR, $estAdministrateur);
    }

    /**
     * Supprime les informations utilisateur de la session.
     *
     * La méthode enlève les clés liées :
     * - à l'identité ;
     * - aux rôles métier ;
     * - aux rôles internes.
     *
     * @return void
     */
    public function deconnecter(): void
    {
        /*
         * On récupère la session courante.
         */
        $session = $this->session();
        if ($session === null) {
            return;
        }

        /*
         * Suppression des informations principales.
         */
        $session->remove(self::CLE_ID);
        $session->remove(self::CLE_PSEUDO);

        /*
         * Suppression des rôles métier.
         */
        $session->remove(self::CLE_ROLE_CHAUFFEUR);
        $session->remove(self::CLE_ROLE_PASSAGER);

        /*
         * Suppression des rôles internes.
         */
        $session->remove(self::CLE_EST_EMPLOYE);
        $session->remove(self::CLE_EST_ADMINISTRATEUR);
    }

    /**
     * Retourne une représentation simple de l'utilisateur connecté.
     *
     * Cette méthode assemble les informations utiles
     * à partir de la session actuelle :
     * identifiant, pseudo, rôles métier et rôles internes.
     *
     * Si les informations minimales ne sont pas disponibles,
     * la méthode renvoie null.
     *
     * Deux clés sont conservées pour l'administrateur :
     * - est_administrateur ;
     * - est_admin.
     *
     * Ce doublon évite de casser du code existant
     * qui utiliserait déjà l'un ou l'autre nom.
     *
     * @return array<string, mixed>|null Tableau utilisateur simplifié ou null.
     */
    public function obtenirUtilisateurConnecte(): ?array
    {
        /*
         * On récupère d'abord l'identifiant et le pseudo
         * via les méthodes dédiées.
         */
        $id = $this->idUtilisateur();
        $pseudo = $this->pseudo();

        /*
         * Si l'une des deux informations manque,
         * on considère qu'il n'y a pas d'utilisateur connecté exploitable.
         */
        if ($id === null || $pseudo === null) {
            return null;
        }

        /*
         * On récupère ensuite la session courante
         * pour lire le reste des rôles.
         */
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        /*
         * Lecture des rôles internes.
         */
        $estEmploye = (bool) $session->get(self::CLE_EST_EMPLOYE, false);
        $estAdministrateur = (bool) $session->get(self::CLE_EST_ADMINISTRATEUR, false);

        /*
         * Construction du tableau utilisateur homogène
         * renvoyé au reste de l'application.
         */
        return [
            'id_utilisateur' => $id,
            'pseudo' => $pseudo,
            'role_chauffeur' => (bool) $session->get(self::CLE_ROLE_CHAUFFEUR, false),
            'role_passager' => (bool) $session->get(self::CLE_ROLE_PASSAGER, false),
            'est_employe' => $estEmploye,
            'est_administrateur' => $estAdministrateur,
            'est_admin' => $estAdministrateur,
        ];
    }

    /**
     * Retourne l'utilisateur connecté ou lève une exception s'il manque.
     *
     * Cette méthode sert quand le code appelant
     * exige explicitement une session valide.
     *
     * Si aucun utilisateur exploitable n'est trouvé,
     * une RuntimeException est levée.
     *
     * @return array<string, mixed> Tableau utilisateur simplifié.
     *
     * @throws RuntimeException Si aucun utilisateur n'est connecté.
     */
    public function exigerUtilisateurConnecte(): array
    {
        /*
         * On récupère la structure utilisateur.
         */
        $utilisateur = $this->obtenirUtilisateurConnecte();

        /*
         * Si rien n'est disponible,
         * on coupe le traitement avec une exception claire.
         */
        if ($utilisateur === null) {
            throw new RuntimeException('Utilisateur non connecté : accès refusé.');
        }

        return $utilisateur;
    }

    /**
     * Met à jour uniquement les rôles métier de l'utilisateur connecté.
     *
     * Cette méthode ne touche pas :
     * - à l'identifiant ;
     * - au pseudo ;
     * - aux rôles internes.
     *
     * Elle sert à garder la session cohérente
     * après une modification du profil utilisateur.
     *
     * @param bool $roleChauffeur Nouveau statut du rôle chauffeur.
     * @param bool $rolePassager Nouveau statut du rôle passager.
     */
    public function mettreAJourRolesUtilisateurConnecte(bool $roleChauffeur, bool $rolePassager): void
    {
        /*
         * On récupère la session courante.
         */
        $session = $this->session();
        if ($session === null) {
            return;
        }

        /*
         * Mise à jour des rôles métier en session.
         */
        $session->set(self::CLE_ROLE_CHAUFFEUR, $roleChauffeur);
        $session->set(self::CLE_ROLE_PASSAGER, $rolePassager);
    }
}