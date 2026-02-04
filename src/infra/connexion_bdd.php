<?php

declare(strict_types=1);

/**
 * Connexion PostgreSQL via PDO.
 * Retourne une instance PDO prête à être utilisée.
 */
function obtenirConnexionBdd(): PDO
{
    $hote = getenv('BDD_HOTE') ?: 'postgresql';
    $port = getenv('BDD_PORT') ?: '5432';
    $nomBase = getenv('BDD_NOM') ?: 'ecoride';
    $utilisateur = getenv('BDD_UTILISATEUR') ?: 'ecoride';
    $motDePasse = getenv('BDD_MOT_DE_PASSE') ?: 'ecoride';

    $dsn = "pgsql:host={$hote};port={$port};dbname={$nomBase}";

    return new PDO(
        $dsn,
        $utilisateur,
        $motDePasse,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}
