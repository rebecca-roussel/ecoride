<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie le bon chargement de la page d'accueil.
 *
 * Pourquoi ce test :
 * on veut commencer par un contrôle simple et utile. L'objectif ici n'est pas
 * encore de tester tout le contenu de la page, mais de vérifier que le point
 * d'entrée principal du site répond correctement.
 *
 * Démarche retenue :
 * on simule une requête HTTP GET vers l'accueil, puis on vérifie que la réponse
 * renvoyée par l'application est un succès.
 */
final class AccueilTest extends WebTestCase
{
    /**
     * Vérifie que la page d'accueil répond correctement.
     *
     * Ce test simule l'ouverture de la page d'accueil dans un navigateur.
     * Si l'application renvoie une réponse HTTP réussie, cela signifie que
     * la route existe, que le contrôleur répond et que le rendu de la page
     * ne plante pas au chargement.
     */
    public function testLaPageAccueilRepondCorrectement(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }
}