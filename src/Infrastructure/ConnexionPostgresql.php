<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use PDOException;
use RuntimeException;

/*
 * Cette classe sert juste à une chose :
 * -> créer une connexion PDO vers PostgreSQL, puis la réutiliser.
 *
 * Pourquoi je fais ça ?
 * - j’ai besoin de parler à la base PostgreSQL pour faire mes requêtes SQL.
 * - évite de recopier "new PDO(...)" partout dans le projet.
 *
 * Idée importante :
 * - La connexion à la base est un passage vers PostgreSQL.
 * - PDO = l’objet PHP qui représente ce passage.
 */
final class ConnexionPostgresql
{
    /*
     * Je garde la connexion dans une propriété.
     * Comme ça, si j’appelle obtenir() plusieurs fois,
     * je ne recrée pas 10 connexions inutiles.
     */
    private ?PDO $pdo = null;

    /*
     * Cette méthode me donne une connexion prête à l’emploi.
     * Si elle existe déjà -> je la renvoie.
     * Sinon -> je la crée.
     */
    public function obtenir(): PDO
    {
        // Si la connexion existe déjà, je la réutilise (plus simple)
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        /*
         * Je récupère les infos de connexion depuis les variables d’environnement.
         *
         * Pourquoi ?
         * - selon l’endroit où je tourne (local / docker / serveur),
         *   l’adresse et les identifiants peuvent changer.
         *
         * Le "??" veut dire :
         * - si la variable n’existe pas, je prends une valeur par défaut.
         */
        $hote = $_ENV['PG_HOST'] ?? 'postgresql';
        $port = $_ENV['PG_PORT'] ?? '5432';
        $base = $_ENV['PG_DB'] ?? 'ecoride';
        $utilisateur = $_ENV['PG_USER'] ?? 'ecoride';
        $mot_de_passe = $_ENV['PG_PASSWORD'] ?? 'ecoride';

        /*
         * DSN = "adresse complète" que PDO utilise pour se connecter.
         * Ici c’est du PostgreSQL, donc ça commence par "pgsql:".
         */
        $dsn = "pgsql:host={$hote};port={$port};dbname={$base}";

        try {
            /*
             * Je crée la connexion PDO.
             *
             * Les options que je mets :
             * - ATTR_ERRMODE => EXCEPTION :
             *   si une requête SQL échoue, je veux une erreur claire (exception),
             *   pas un simple "false" silencieux.
             *
             * - ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC :
             *   quand je fais un SELECT, je veux des tableaux associatifs :
             *   ['id_utilisateur' => 1, 'pseudo' => '...']
             *   plutôt que des tableaux avec des index numériques.
             */
            $this->pdo = new PDO(
                $dsn,
                $utilisateur,
                $mot_de_passe,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            /*
             * Si PostgreSQL est éteint, mal configuré, ou si le mot de passe est faux,
             * PDO va lever une exception.
             *
             * Je renvoie une RuntimeException plus simple à comprendre,
             * mais je garde l’erreur d’origine dedans (pour débugger si besoin).
             */
            throw new RuntimeException(
                "Connexion PostgreSQL impossible (vérifie Docker, l’hôte, le port, la base, l’utilisateur).",
                0,
                $e
            );
        }

        // Je renvoie la connexion prête à être utilisée dans les services
        return $this->pdo;
    }
}
