<?php
declare(strict_types=1);

namespace App\Infra;

use PDO;
use PDOException;
use RuntimeException;

final class ConnexionPostgresql
{
    private ?PDO $pdo = null;

    public function obtenir(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $hote = $_ENV['PG_HOST'] ?? 'postgresql';
        $port = $_ENV['PG_PORT'] ?? '5432';
        $base = $_ENV['PG_DB'] ?? 'ecoride';
        $utilisateur = $_ENV['PG_USER'] ?? 'ecoride';
        $mot_de_passe = $_ENV['PG_PASSWORD'] ?? 'ecoride';

        $dsn = "pgsql:host={$hote};port={$port};dbname={$base}";

        try {
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
            throw new RuntimeException("Connexion PostgreSQL impossible : " . $e->getMessage(), 0, $e);
        }

        return $this->pdo;
    }
}

