<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Ce premier test ne vérifie pas encore une règle métier d'EcoRide.
 * Il sert d'abord à confirmer que PHPUnit est bien installé,
 * que le dossier tests est pris en compte
 * et que l'exécution d'un test fonctionne dans le projet.
 */
final class VerificationInitialeTest extends TestCase
{
    public function testLeFrameworkDeTestFonctionne(): void
    {
        $this->assertSame(4, 2 + 2);
    }
}
