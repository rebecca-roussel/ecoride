<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class SessionUtilisateur
{
    /*
      PLAN (SessionUtilisateur) :

      1) Pourquoi ce service existe
         - je centralise tout ce qui touche à la session utilisateur
         - comme ça je ne répète pas du code dans tous les contrôleurs

      2) Ce que je stocke en session
         - un identifiant utilisateur (id_utilisateur)
         - un pseudo (pour afficher “Bienvenue …” sans requête à chaque fois)

      3) Les méthodes utiles
         - estConnecte : est-ce que j’ai un id valide dans la session
         - connecter : je crée la session (après connexion / inscription)
         - deconnecter : je supprime les infos de session
         - idUtilisateur : je récupère l’id en entier (ou null si pas valable)
         - pseudo : je récupère le pseudo (ou null si vide)
    */

    /*
      Clés de session
      - ce sont les noms exacts qu’on met dans la session
      - j’utilise des constantes pour éviter les fautes de frappe
      - si je change un nom, je le change à un seul endroit
    */
    private const CLE_ID = 'utilisateur_id';
    private const CLE_PSEUDO = 'utilisateur_pseudo';

    /*
      RequestStack
      - Symfony me donne la session via le RequestStack
      - ça marche aussi bien en GET qu’en POST
    */
    public function __construct(private RequestStack $requestStack)
    {
    }

    /*
      Est-ce que l'utilisateur est connecté
      - je considère connecté si j’ai un idUtilisateur non null
      - l'idUtilisateur() fait déjà les vérifications et le “nettoyage”
    */
    public function estConnecte(): bool
    {
        return $this->idUtilisateur() !== null;
    }

    /*
      Récupère le pseudo en session
      - retourne null si pas de session ou pseudo invalide
      - je “trim” pour éviter un pseudo avec juste des espaces
    */
    public function pseudo(): ?string
    {
        $session = $this->requestStack->getSession();
        $pseudo = $session?->get(self::CLE_PSEUDO);

        if (!is_string($pseudo)) {
            return null;
        }

        $pseudoNettoye = trim($pseudo);

        return $pseudoNettoye !== '' ? $pseudoNettoye : null;
    }

    /*
      Connecter un utilisateur
      - je stocke l'id et le pseudo dans la session
      - si l’id n’est pas positif ou si le pseudo est vide, je ne stocke rien
      - ça évite de créer une session incohérente
    */
    public function connecter(int $idUtilisateur, string $pseudo): void
    {
        $session = $this->requestStack->getSession();
        if (null === $session) {
            return;
        }

        $pseudoNettoye = trim($pseudo);
        if ($idUtilisateur <= 0 || $pseudoNettoye === '') {
            // Sécurité : on évite de stocker une session incohérente
            return;
        }

        $session->set(self::CLE_ID, $idUtilisateur);
        $session->set(self::CLE_PSEUDO, $pseudoNettoye);
    }

    /*
      Déconnecter
      - je supprime les infos que j’ai mises en session
      - je ne détruis pas toute la session globale, je retire juste mes clés
    */
    public function deconnecter(): void
    {
        $session = $this->requestStack->getSession();
        if (null === $session) {
            return;
        }

        $session->remove(self::CLE_ID);
        $session->remove(self::CLE_PSEUDO);
    }

    /*
      Récupère l'id utilisateur depuis la session
      - je veux un entier positif
      - selon le contexte, Symfony peut me renvoyer un int ou une string
      - donc je gère les deux cas
      - si ce n’est pas valide, je renvoie null
    */
    public function idUtilisateur(): ?int
    {
        $session = $this->requestStack->getSession();
        $id = $session?->get(self::CLE_ID);

        // Cas 1 : déjà un int
        if (is_int($id)) {
            return $id > 0 ? $id : null;
        }

        // Cas 2 : une string numérique ("10")
        if (is_string($id) && ctype_digit($id)) {
            $idInt = (int) $id;
            return $idInt > 0 ? $idInt : null;
        }

        // Sinon : pas connecté / session vide / valeur bizarre
        return null;
    }
}

