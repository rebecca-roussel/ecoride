<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SessionUtilisateur
{
    /*
      PLAN(SessionUtilisateur)

      OBJECTIF :
         - Centraliser toute la gestion de la session utilisateur.
         - On ne manipule jamais $_SESSION directement dans les contrôleurs.

      RÔLE :
      - Savoir si un utilisateur est connecté
      - Connaître son id et son pseudo
      - Connaître ses rôles (chauffeur / passager)
      - Connaître ses rôles internes (employé / administrateur)
      - Ouvrir et fermer la session proprement
    */


    /*
      1) CLÉS DE SESSION

      On définit ici les noms EXACTS utilisés dans la session et éviter
       les fautes de frappe ailleurs.
    */
    private const CLE_ID = 'utilisateur_id';
    private const CLE_PSEUDO = 'utilisateur_pseudo';
    private const CLE_ROLE_CHAUFFEUR = 'utilisateur_role_chauffeur';
    private const CLE_ROLE_PASSAGER = 'utilisateur_role_passager';
    private const CLE_EST_EMPLOYE = 'utilisateur_est_employe';
    private const CLE_EST_ADMINISTRATEUR = 'utilisateur_est_administrateur';


    /* Injection de RequestStack (accès à la requête courante et à la session). */
    public function __construct(private RequestStack $requestStack)
    {
    }


    /*
      3) MÉTHODE INTERNE : RÉCUPÉRER LA SESSION

      - On passe par la requête courante.
      - Si aucune requête n’existe  pas de session.
      - Si la session n’est pas active on retourne null.
    */
    private function session(): ?SessionInterface
    {
        $requete = $this->requestStack->getCurrentRequest();

        if ($requete === null) {
            return null;
        }

        return $requete->hasSession()
            ? $requete->getSession()
            : null;
    }


    /*
      4) SAVOIR SI L’UTILISATEUR EST CONNECTÉ

      - Il est considéré connecté si un id valide est présent.
    */
    public function estConnecte(): bool
    {
        return $this->idUtilisateur() !== null;
    }


    /*
      5) RÉCUPÉRER L’ID UTILISATEUR

      sécurité :
      - Si ce n’est pas un entier valide → null
      - Si valeur <= 0 → null
    */
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


    /*
      6) RÉCUPÉRER LE PSEUDO

      On vérifie que c’est bien une chaîne non vide.
    */
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
      7) CONNECTER UN UTILISATEUR

      - Cette méthode ouvre la session.
      - Elle enregistre toutes les informations utiles.

      Important :
      PAS de vérification de mot de passe,
      car fait avant dans le contrôleur.
    */
    public function connecter(
        int $idUtilisateur,
        string $pseudo,
        bool $roleChauffeur = false,
        bool $rolePassager = true,
        bool $estEmploye = false,
        bool $estAdministrateur = false
    ): void {
        $session = $this->session();
        if ($session === null) {
            return;
        }

        if ($idUtilisateur <= 0 || trim($pseudo) === '') {
            return;
        }

        $session->set(self::CLE_ID, $idUtilisateur);
        $session->set(self::CLE_PSEUDO, trim($pseudo));
        $session->set(self::CLE_ROLE_CHAUFFEUR, $roleChauffeur);
        $session->set(self::CLE_ROLE_PASSAGER, $rolePassager);
        $session->set(self::CLE_EST_EMPLOYE, $estEmploye);
        $session->set(self::CLE_EST_ADMINISTRATEUR, $estAdministrateur);
    }


    /*
      8) DÉCONNECTER

      -  On supprime toutes les clés.
       - On ne détruit pas toute la session globale.
    */
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


    /*
      9) RÉCUPÉRER UN "OBJET UTILISATEUR" SIMPLE

      - Méthode pratique pour Twig ou contrôleurs.
      - Retourne un tableau structuré.
    */
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

        return [
            'id_utilisateur' => $id,
            'pseudo' => $pseudo,
            'role_chauffeur' => (bool) $session->get(self::CLE_ROLE_CHAUFFEUR, false),
            'role_passager' => (bool) $session->get(self::CLE_ROLE_PASSAGER, false),
            'est_employe' => (bool) $session->get(self::CLE_EST_EMPLOYE, false),
            'est_administrateur' => (bool) $session->get(self::CLE_EST_ADMINISTRATEUR, false),
        ];
    }


    /*
      10) OBLIGER LA CONNEXION
        - Si personne n’est connecté, blocage immédiat.
    */
    public function exigerUtilisateurConnecte(): array
    {
        $utilisateur = $this->obtenirUtilisateurConnecte();

        if ($utilisateur === null) {
            throw new RuntimeException('Utilisateur non connecté : accès refusé.');
        }

        return $utilisateur;
    }


    /*
      11) METTRE À JOUR LES RÔLES FONCTIONNELS
      (chauffeur / passager)
    */
    public function mettreAJourRolesUtilisateurConnecte(
        bool $roleChauffeur,
        bool $rolePassager
    ): void {
        $session = $this->session();
        if ($session === null) {
            return;
        }

        $session->set(self::CLE_ROLE_CHAUFFEUR, $roleChauffeur);
        $session->set(self::CLE_ROLE_PASSAGER, $rolePassager);
    }


    /*
      12) SAVOIR SI EMPLOYÉ
    */
    public function estEmploye(): bool
    {
        $session = $this->session();
        if ($session === null) {
            return false;
        }

        return (bool) $session->get(self::CLE_EST_EMPLOYE, false);
    }


    /*
      13) SAVOIR SI ADMINISTRATEUR
    */
    public function estAdministrateur(): bool
    {
        $session = $this->session();
        if ($session === null) {
            return false;
        }

        return (bool) $session->get(self::CLE_EST_ADMINISTRATEUR, false);
    }
}
