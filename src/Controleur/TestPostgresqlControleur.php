<?php
declare(strict_types=1);

namespace App\Controleur;

use App\Infrastructure\ConnexionPostgresql;
use PDO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/*
 * Ce contrôleur sert uniquement de test.
 *
 * Mon objectif :
 * - vérifier que Symfony arrive à parler à PostgreSQL via PDO
 * - sans front, sans logique métier, sans “covoiturage” pour l’instant
 *
 * Si cette route marche, ça prouve que :
 * Symfony -> mon code PHP -> ConnexionPostgresql -> PDO -> PostgreSQL
 * fonctionne correctement.
 */
final class TestPostgresqlControleur
{
    /*
     * Cette annotation (Route) dit à Symfony :
     * - quand quelqu’un va sur /test-postgresql
     * - en méthode GET (navigateur)
     * alors Symfony appelle cette classe.
     */
    #[Route('/test-postgresql', name: 'test_postgresql', methods: ['GET'])]
    public function __invoke(ConnexionPostgresql $connexion): JsonResponse
    {
        /*
         * Symfony me fournit automatiquement $connexion (injection).
         * ConnexionPostgresql me donne un objet PDO déjà configuré.
         */
        $pdo = $connexion->obtenir();

        /*
         * Je fais une requête volontairement simple :
         * compter combien il y a d’utilisateurs dans la table utilisateur.
         *
         * Pourquoi cette table ?
         * - parce que je sais qu’elle existe dans mon schema.sql
         * - et je sais que j’ai inséré des données de démo
         */
        $stmt = $pdo->query('SELECT COUNT(*) AS total FROM utilisateur');

        /*
         * fetch() récupère la première ligne du résultat.
         * Comme j’ai mis "AS total", je récupère un tableau avec la clé "total".
         */
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * Je renvoie une réponse JSON simple, lisible dans le navigateur.
         * Exemple attendu :
         * { "ok": true, "utilisateurs": 5 }
         *
         * Le "?? 0" évite une erreur si jamais la clé n’existe pas (sécurité).
         * Le (int) force le résultat en nombre.
         */
        return new JsonResponse([
            'ok' => true,
            'utilisateurs' => (int) ($ligne['total'] ?? 0),
        ]);
    }
}
