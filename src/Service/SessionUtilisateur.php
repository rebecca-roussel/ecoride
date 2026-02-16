<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SessionUtilisateur
{
    /*
      PLAN (SessionUtilisateur) :

      1) Pourquoi ce service existe
         - centraliser tout ce qui touche à la session utilisateur
         - éviter de répéter du code dans tous les contrôleurs

      2) Ce que je stocke en session
         - id_utilisateur (int)
         - pseudo (string)
         - rôles fonctionnels (chauffeur / passager)
         - rôles internes (admin / employé)
           -> indispensables pour rediriger vers le bon espace
           -> et sécuriser les pages /admin et /employe

      3) Méthodes utiles
         - estConnecte()
         - connecter()
         - deconnecter()
         - idUtilisateur()
         - pseudo()
         - estAdmin()
         - estEmploye()
         - obtenirUtilisateurConnecte()
         - exigerUtilisateurConnecte()
         - mettreAJourRolesUtilisateurConnecte()
    */

    /*
      Clés de session (noms exacts)
    */
    private const CLE_ID = 'utilisateur_id';
    private const CLE_PSEUDO = 'utilisateur_pseudo';

    private const CLE_ROLE_CHAUFFEUR = 'utilisateur_role_chauffeur';
    private const CLE_ROLE_PASSAGER  = 'utilisateur_role_passager';

    // Rôles internes (issus des tables employe / administrateur)
    private const CLE_EST_ADMIN   = 'utilisateur_est_admin';
    private const CLE_EST_EMPLOYE = 'utilisateur_est_employe';

    public function __construct(private RequestStack $requestStack)
    {
    }

    /*
      Session Symfony
      - on passe par la requête courante
      - si pas de requête (cas rare), pas de session
    */
    private function session(): ?SessionInterface
    {
        $requete = $this->requestStack->getCurrentRequest();
        if ($requete === null) {
            return null;
        }

        return $requete->hasSession() ? $requete->getSession() : null;
    }

    public function estConnecte(): bool
    {
        return $this->idUtilisateur() !== null;
    }

    public function pseudo(): ?string
    {
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        $pseudo = $session->get(self::CLE_PSEUDO);

        if (!is_string($pseudo)) {
            return null;
        }

        $pseudoNettoye = trim($pseudo);

        return $pseudoNettoye !== '' ? $pseudoNettoye : null;
    }

    public function estAdmin(): bool
    {
        $session = $this->session();
        if ($session === null) {
            return false;
        }

        return (bool) $session->get(self::CLE_EST_ADMIN, false);
    }

    public function estEmploye(): bool
    {
        $session = $this->session();
        if ($session === null) {
            return false;
        }

        return (bool) $session->get(self::CLE_EST_EMPLOYE, false);
    }

    /*
      Connecter
      - on passe les rôles tels qu'ils existent en base (source de vérité)
      - on stocke aussi admin / employé pour choisir le bon espace après connexion
    */
    public function connecter(
        int $idUtilisateur,
        string $pseudo,
        bool $roleChauffeur = false,
        bool $rolePassager = false,
        bool $estAdmin = false,
        bool $estEmploye = false
    ): void {
        $session = $this->session();
        if ($session === null) {
            return;
        }

        $pseudoNettoye = trim($pseudo);
        if ($idUtilisateur <= 0 || $pseudoNettoye === '') {
            return;
        }

        $session->set(self::CLE_ID, $idUtilisateur);
        $session->set(self::CLE_PSEUDO, $pseudoNettoye);

        $session->set(self::CLE_ROLE_CHAUFFEUR, $roleChauffeur);
        $session->set(self::CLE_ROLE_PASSAGER, $rolePassager);

        $session->set(self::CLE_EST_ADMIN, $estAdmin);
        $session->set(self::CLE_EST_EMPLOYE, $estEmploye);
    }

    public function deconnecter(): void
    {
        $session = $this->session();
        if ($session === null) {
            return;
        }

        $session->remove(self::CLE_ID);
        $session->remove(self::CLE_PSEUDO);

        $session->remove(self::CLE_ROLE_CHAUFFEUR);
        $session->remove(self::CLE_ROLE_PASSAGER);

        $session->remove(self::CLE_EST_ADMIN);
        $session->remove(self::CLE_EST_EMPLOYE);
    }

    public function idUtilisateur(): ?int
    {
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        $id = $session->get(self::CLE_ID);

        if (is_int($id)) {
            return $id > 0 ? $id : null;
        }

        if (is_string($id) && ctype_digit($id)) {
            $idInt = (int) $id;
            return $idInt > 0 ? $idInt : null;
        }

        return null;
    }

    public function obtenirUtilisateurConnecte(): ?array
    {
        $idUtilisateur = $this->idUtilisateur();
        $pseudo = $this->pseudo();

        if ($idUtilisateur === null || $pseudo === null) {
            return null;
        }

        $session = $this->session();
        if ($session === null) {
            return null;
        }

        return [
            'id_utilisateur' => $idUtilisateur,
            'pseudo' => $pseudo,

            'role_chauffeur' => (bool) $session->get(self::CLE_ROLE_CHAUFFEUR, false),
            'role_passager' => (bool) $session->get(self::CLE_ROLE_PASSAGER, false),

            'est_admin' => (bool) $session->get(self::CLE_EST_ADMIN, false),
            'est_employe' => (bool) $session->get(self::CLE_EST_EMPLOYE, false),
        ];
    }

    public function mettreAJourRolesUtilisateurConnecte(bool $roleChauffeur, bool $rolePassager): void
    {
        $session = $this->session();
        if ($session === null) {
            return;
        }

        $session->set(self::CLE_ROLE_CHAUFFEUR, $roleChauffeur);
        $session->set(self::CLE_ROLE_PASSAGER, $rolePassager);
    }

    public function exigerUtilisateurConnecte(): array
    {
        $utilisateur = $this->obtenirUtilisateurConnecte();

        if ($utilisateur === null) {
            throw new RuntimeException('Utilisateur non connecté : accès refusé.');
        }

        return $utilisateur;
    }
}
