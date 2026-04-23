<?php

declare(strict_types=1);

namespace App\Tests;

use App\Service\ConnexionPostgresql;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire du service ConnexionPostgresql.
 *
 * Objectif du test :
 * vérifier le comportement du service quand la variable
 * d'environnement POSTGRES_PASSWORD est vide.
 *
 * Règle testée :
 * dans ce cas précis, le service ne doit pas essayer
 * d'aller plus loin dans la création de PDO.
 * Il doit interrompre le traitement immédiatement
 * avec une RuntimeException explicite.
 *
 * Intérêt de ce test :
 * ce contrôle isole une règle simple mais sensible.
 * Il permet de vérifier qu'une configuration incomplète
 * est détectée très tôt, avec un message compréhensible.
 */
final class ConnexionPostgresqlTest extends TestCase
{
    /**
     * Vérifie qu'une erreur claire est levée
     * quand POSTGRES_PASSWORD est vide.
     *
     * Déroulement du test :
     * 1. forcer la variable d'environnement POSTGRES_PASSWORD à vide ;
     * 2. instancier le service ConnexionPostgresql ;
     * 3. annoncer à PHPUnit qu'une RuntimeException est attendue ;
     * 4. vérifier que le message contient bien
     *    "POSTGRES_PASSWORD est vide" ;
     * 5. appeler obtenirPdo() pour déclencher le comportement testé.
     *
     * Résultat attendu :
     * le service refuse de poursuivre la création de la connexion
     * et lève immédiatement l'exception prévue.
     */
    public function testMotDePassePostgresqlVide(): void
    {
        /*
         * On vide volontairement la variable d'environnement
         * dans $_ENV pour simuler une configuration invalide.
         */
        $_ENV['POSTGRES_PASSWORD'] = '';

        /*
         * On vide aussi la variable côté environnement système.
         * Le service lit d'abord $_ENV puis getenv(),
         * donc on aligne les deux sources sur le même cas de test.
         */
        putenv('POSTGRES_PASSWORD=');

        /*
         * On crée l'objet que l'on veut tester.
         * À ce stade, aucune connexion PDO n'est encore créée.
         */
        $service = new ConnexionPostgresql();

        /*
         * PHPUnit doit maintenant attendre une RuntimeException.
         * Le test sera considéré comme correct seulement si cette
         * exception est réellement levée lors de l'appel suivant.
         */
        $this->expectException(\RuntimeException::class);

        /*
         * On vérifie aussi le contenu du message.
         * Cela permet de contrôler que l'erreur levée
         * correspond bien au cas du mot de passe vide.
         */
        $this->expectExceptionMessage('POSTGRES_PASSWORD est vide');

        /*
         * Cet appel déclenche la lecture des variables d'environnement.
         * Comme le mot de passe a été vidé juste avant,
         * le service doit interrompre le traitement ici.
         */
        $service->obtenirPdo();
    }
}