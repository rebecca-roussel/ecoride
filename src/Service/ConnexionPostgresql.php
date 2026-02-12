<?php

declare(strict_types=1);

namespace App\Service;

final class ConnexionPostgresql
{
    private ?\PDO $pdo = null;

    public function obtenirPdo(): \PDO
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        $hote = $this->lireEnv('POSTGRES_HOST', 'postgresql');
        $port = $this->lireEnv('POSTGRES_PORT', '5432');
        $base = $this->lireEnv('POSTGRES_DB', 'ecoride');
        $utilisateur = $this->lireEnv('POSTGRES_USER', 'ecoride');
        $motDePasse = $this->lireEnv('POSTGRES_PASSWORD', '');

        if ('' === $motDePasse) {
            throw new \RuntimeException('POSTGRES_PASSWORD est vide : la connexion PDO échouera.');
        }

        $dsn = "pgsql:host={$hote};port={$port};dbname={$base}";

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

    private function lireEnv(string $cle, string $valeurParDefaut): string
    {
        $valeur = $_ENV[$cle] ?? getenv($cle) ?: null;

        if (null === $valeur || '' === trim((string) $valeur)) {
            return $valeurParDefaut;
        }

        return (string) $valeur;
    }
}
