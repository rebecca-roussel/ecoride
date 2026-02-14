<?php

declare(strict_types=1);

namespace App\Service;

final class ConnexionPostgresql
{
    /*
      PLAN (ConnexionPostgresql) :

      1) Pourquoi ce service existe
         - je centralise la création de la connexion PDO vers PostgreSQL
         - comme ça, tous mes services de persistance (utilisateur, covoiturage, etc.)
           utilisent la même connexion

      2) Principe important
         - je garde une seule connexion PDO (singleton “maison”)
         - au premier appel : je crée PDO
         - aux appels suivants : je réutilise le même PDO

      3) Pourquoi c’est pratique
         - moins de code dupliqué
         - moins de risques d’options PDO incohérentes
         - plus simple à déboguer si la connexion casse
    */

    // La connexion PDO est stockée ici pour être réutilisée
    private ?\PDO $pdo = null;

    /*
      Fournit un PDO prêt à l’emploi
      - si PDO existe déjà, je le renvoie
      - sinon, je lis les variables d'environnement et je le crée
    */
    public function obtenirPdo(): \PDO
    {
        // 1) Si on a déjà un PDO, on le réutilise
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        /*
          2) Lecture de la configuration

          Les valeurs viennent de :
          - $_ENV (Symfony)
          - ou getenv() (environnement système / docker)
          Si une variable est absente, je mets une valeur par défaut.
        */
        $hote = $this->lireEnv('POSTGRES_HOST', 'postgresql');
        $port = $this->lireEnv('POSTGRES_PORT', '5432');
        $base = $this->lireEnv('POSTGRES_DB', 'ecoride');
        $utilisateur = $this->lireEnv('POSTGRES_USER', 'ecoride');
        $motDePasse = $this->lireEnv('POSTGRES_PASSWORD', '');

        /*
          Sécurité
          - si le mot de passe est vide, je préfère planter tout de suite
          - sinon je vais perdre du temps avec une “erreur de connexion” pas claire
        */
        if ('' === $motDePasse) {
            throw new \RuntimeException('POSTGRES_PASSWORD est vide : la connexion PDO échouera.');
        }

        /*
          DSN PostgreSQL
          - pgsql:host=...;port=...;dbname=...
          - ici on ne met pas le mot de passe dans le DSN, il est passé à part
        */
        $dsn = "pgsql:host={$hote};port={$port};dbname={$base}";

        /*
          3) Création de PDO avec des options importantes

          - ERRMODE_EXCEPTION
            si une requête SQL échoue, je veux une exception 

          - DEFAULT_FETCH_MODE = FETCH_ASSOC
            mes fetch() renvoient des tableaux associatifs directement (clé = nom de colonne)

          - EMULATE_PREPARES = false
            je demande des requêtes préparées côté PostgreSQL

        */
        $this->pdo = new \PDO(
            $dsn,
            $utilisateur,
            $motDePasse,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $this->pdo;
    }

    /*
      Lit une variable d'environnement avec une valeur par défaut

      - $cle : nom de la variable (ex : POSTGRES_HOST)
      - $valeurParDefaut : utilisée si la variable est absente ou vide

      Pourquoi je “trim” :
      - pour éviter qu'une valeur "   " soit considérée comme valable
    */
    private function lireEnv(string $cle, string $valeurParDefaut): string
    {
        $valeur = $_ENV[$cle] ?? getenv($cle) ?: null;

        if (null === $valeur || '' === trim((string) $valeur)) {
            return $valeurParDefaut;
        }

        return (string) $valeur;
    }
}
