<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie le bon chargement de la page d'accueil.
 *
 * Démarche retenue :
 * on commence par un test simple et utile, sans chercher à tout contrôler
 * d'un coup. L'objectif ici est de vérifier que le point d'entrée principal
 * du site répond correctement.
 *
 * Ce que ce test valide :
 * - la route d'accueil existe ;
 * - le contrôleur répond ;
 * - le rendu de la page ne plante pas.
 */
final class AccueilTest extends WebTestCase
{
    /**
     * Vérifie que la page d'accueil répond correctement.
     *
     * On simule une requête HTTP GET vers l'URL "/".
     * Si la réponse est un succès, cela confirme que l'accueil
     * de l'application se charge correctement.
     */
    public function testLaPageAccueilRepondCorrectement(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }
}