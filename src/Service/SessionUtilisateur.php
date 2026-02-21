<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SessionUtilisateur
{
    /*
      PLAN (SessionUtilisateur)

      Objectif :
      - Centraliser toute la gestion de la session utilisateur
      - Ne jamais manipuler $_SESSION directement dans les contrôleurs

      Ce que je stocke en session :
      - id utilisateur + pseudo
      - rôles fonctionnels (chauffeur / passager)
      - rôles internes (employé / administrateur) pour sécuriser et rediriger
    */

    // 1) CLÉS DE SESSION (noms exacts)
    private const CLE_ID = 'utilisateur_id';
    private const CLE_PSEUDO = 'utilisateur_pseudo';

    private const CLE_ROLE_CHAUFFEUR = 'utilisateur_role_chauffeur';
    private const CLE_ROLE_PASSAGER = 'utilisateur_role_passager';

    private const CLE_EST_EMPLOYE = 'utilisateur_est_employe';
    private const CLE_EST_ADMINISTRATEUR = 'utilisateur_est_administrateur';

    public function __construct(private RequestStack $requestStack)
    {
    }

    // 2) Récupérer la session courante
    private function session(): ?SessionInterface
    {
        $requete = $this->requestStack->getCurrentRequest();

        if ($requete === null) {
            return null;
        }

        return $requete->hasSession() ? $requete->getSession() : null;
    }

    // 3) Connexion
    public function estConnecte(): bool
    {
        return $this->idUtilisateur() !== null;
    }

    public function idUtilisateur(): ?int
    {
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        $id = $session->get(self::CLE_ID);

        if (is_int($id) && $id > 0) {
            return $id;
        }

        if (is_string($id) && ctype_digit($id)) {
            $idInt = (int) $id;
            return $idInt > 0 ? $idInt : null;
        }

        return null;
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

    // 4) Rôles internes
    public function estEmploye(): bool
    {
        $session = $this->session();
        if ($session === null) {
            return false;
        }

        return (bool) $session->get(self::CLE_EST_EMPLOYE, false);
    }

    public function estAdmin(): bool
    {
        $session = $this->session();
        if ($session === null) {
            return false;
        }

        return (bool) $session->get(self::CLE_EST_ADMINISTRATEUR, false);
    }

    // Alias éventuel, si du code existant l’utilise encore
    public function estAdministrateur(): bool
    {
        return $this->estAdmin();
    }

    // 5) Ouvrir/fermer la session
    public function connecter(
        int $idUtilisateur,
        string $pseudo,
        bool $roleChauffeur = false,
        bool $rolePassager = false,
        bool $estEmploye = false,
        bool $estAdministrateur = false
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

        $session->set(self::CLE_EST_EMPLOYE, $estEmploye);
        $session->set(self::CLE_EST_ADMINISTRATEUR, $estAdministrateur);
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

        $session->remove(self::CLE_EST_EMPLOYE);
        $session->remove(self::CLE_EST_ADMINISTRATEUR);
    }

    // 6) Infos pratiques
    public function obtenirUtilisateurConnecte(): ?array
    {
        $id = $this->idUtilisateur();
        $pseudo = $this->pseudo();

        if ($id === null || $pseudo === null) {
            return null;
        }

        $session = $this->session();
        if ($session === null) {
            return null;
        }

        $estEmploye = (bool) $session->get(self::CLE_EST_EMPLOYE, false);
        $estAdministrateur = (bool) $session->get(self::CLE_EST_ADMINISTRATEUR, false);

        return [
            'id_utilisateur' => $id,
            'pseudo' => $pseudo,

            'role_chauffeur' => (bool) $session->get(self::CLE_ROLE_CHAUFFEUR, false),
            'role_passager' => (bool) $session->get(self::CLE_ROLE_PASSAGER, false),

            // deux clés pour éviter de casser du code existant
            'est_employe' => $estEmploye,
            'est_administrateur' => $estAdministrateur,
            'est_admin' => $estAdministrateur,
        ];
    }

    public function exigerUtilisateurConnecte(): array
    {
        $utilisateur = $this->obtenirUtilisateurConnecte();

        if ($utilisateur === null) {
            throw new RuntimeException('Utilisateur non connecté : accès refusé.');
        }

        return $utilisateur;
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
}
