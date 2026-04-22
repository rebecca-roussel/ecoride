<?php

declare(strict_types=1);

namespace App\Tests;

use App\Service\ConnexionPostgresql;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie un comportement simple du service de connexion PostgreSQL.
 *
 * Démarche retenue :
 * on ne cherche pas ici à tester une vraie connexion vers la base.
 * On vérifie d'abord une règle déjà présente dans le service :
 * si le mot de passe PostgreSQL est vide, on bloque tout de suite
 * avec une erreur explicite.
 *
 * Intérêt du test :
 * ce contrôle reste rapide, stable et indépendant d'un conteneur
 * PostgreSQL en cours d'exécution.
 */
final class ConnexionPostgresqlTest extends TestCase
{
    /**
     * Sauvegarde l'état initial de POSTGRES_PASSWORD avant le test.
     *
     * @var string|false
     */
    private string|false $ancienneValeurGetenv = false;

    /**
     * Indique si POSTGRES_PASSWORD existait déjà dans $_ENV.
     *
     * @var bool
     */
    private bool $ancienneValeurEnvExiste = false;

    /**
     * Sauvegarde l'ancienne valeur de POSTGRES_PASSWORD dans $_ENV.
     *
     * @var string|null
     */
    private ?string $ancienneValeurEnv = null;

    /**
     * Sauvegarde l'environnement avant chaque test.
     */
    protected function setUp(): void
    {
        $this->ancienneValeurGetenv = getenv('POSTGRES_PASSWORD');
        $this->ancienneValeurEnvExiste = array_key_exists('POSTGRES_PASSWORD', $_ENV);
        $this->ancienneValeurEnv = $this->ancienneValeurEnvExiste
            ? (string) $_ENV['POSTGRES_PASSWORD']
            : null;
    }

    /**
     * Restaure l'environnement après chaque test.
     */
    protected function tearDown(): void
    {
        if (false === $this->ancienneValeurGetenv) {
            putenv('POSTGRES_PASSWORD');
        } else {
            putenv('POSTGRES_PASSWORD=' . $this->ancienneValeurGetenv);
        }

        if ($this->ancienneValeurEnvExiste) {
            $_ENV['POSTGRES_PASSWORD'] = $this->ancienneValeurEnv;
        } else {
            unset($_ENV['POSTGRES_PASSWORD']);
        }
    }

    /**
     * Vérifie qu'une erreur claire est levée si POSTGRES_PASSWORD est vide.
     */
    public function testObtientUneErreurClaireQuandLeMotDePassePostgresqlEstVide(): void
    {
        $_ENV['POSTGRES_PASSWORD'] = '';
        putenv('POSTGRES_PASSWORD=');

        $service = new ConnexionPostgresql();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('POSTGRES_PASSWORD est vide');

        $service->obtenirPdo();
    }
}