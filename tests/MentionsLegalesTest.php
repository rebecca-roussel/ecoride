<?php

declare(strict_types=1);

/*
 * Ce fichier appartient à l'espace de noms App\Tests.
 * Cela permet de ranger cette classe dans le dossier des tests
 * de l'application et de la faire retrouver correctement
 * par Composer et PHPUnit.
 */
namespace App\Tests;

/*
 * WebTestCase est la classe de base fournie par Symfony
 * pour écrire des tests fonctionnels.
 *
 * Un test fonctionnel sert à vérifier le comportement visible
 * d'une partie de l'application, par exemple le chargement
 * d'une page accessible par une route.
 */
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test fonctionnel de la page des mentions légales.
 *
 * Objectif :
 * vérifier que la route /mentions-legales répond correctement
 * et que la page peut s'afficher sans erreur.
 *
 * Ce test reste volontairement simple.
 * Il permet de contrôler que :
 * - la route existe ;
 * - le contrôleur répond ;
 * - le rendu de la page ne provoque pas d'erreur.
 */
final class MentionsLegalesTest extends WebTestCase
{
    /**
     * Vérifie que la page des mentions légales répond correctement.
     *
     * Déroulement :
     * - on crée un navigateur de test Symfony ;
     * - on simule une requête HTTP GET vers /mentions-legales ;
     * - on vérifie que la réponse renvoyée par l'application
     *   est un succès HTTP.
     *
     * Si ce test passe, cela confirme que la page est accessible
     * et que son affichage fonctionne correctement.
     */
    public function testPageMentionsLegalesRepondCorrectement(): void
    {
        /*
         * On crée le client de test Symfony.
         *
         * Ce client joue le rôle d'un navigateur
         * et permet d'envoyer des requêtes HTTP
         * vers l'application pendant le test.
         */
        $client = static::createClient();

        /*
         * On simule ici une requête HTTP GET
         * vers l'URL /mentions-legales.
         *
         * GET correspond à une demande de lecture d'une page.
         * Cette ligne revient donc à tester l'ouverture
         * de la page des mentions légales.
         */
        $client->request('GET', '/mentions-legales');

        /*
         * On vérifie que la réponse renvoyée par l'application
         * correspond à un succès HTTP.
         *
         * Cela signifie que Symfony a bien trouvé la route,
         * exécuté le contrôleur, puis rendu la page sans erreur.
         */
        self::assertResponseIsSuccessful();
    }
}