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

      3) Méthodes utiles
         - estConnecte()
         - connecter()
         - deconnecter()
         - idUtilisateur()
         - pseudo()
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
    private const CLE_ROLE_PASSAGER = 'utilisateur_role_passager';

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

        // Symfony : la session n’existe que si elle est démarrée/activée
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

    /*
      Connecter
      - rôles optionnels (valeurs par défaut) pour éviter des appels trop fragiles
      - MAIS : idéalement, tu passes les vraies valeurs depuis la BDD / formulaire
    */
    public function connecter(
        int $idUtilisateur,
        string $pseudo,
        bool $roleChauffeur = false,
        bool $rolePassager = true
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
    }

    public function idUtilisateur(): ?int
    {
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        $id = $session->get(self::CLE_ID);

        // Cas 1 : int
        if (is_int($id)) {
            return $id > 0 ? $id : null;
        }

        // Cas 2 : string numérique
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

