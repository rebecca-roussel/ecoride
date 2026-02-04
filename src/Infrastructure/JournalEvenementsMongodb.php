<?php
declare(strict_types=1);

namespace App\Infrastructure;

use MongoDB\Client;
use MongoDB\Collection;
use RuntimeException;
use Throwable;

/*
 * Ce fichier sert à écrire un "journal d'événements" dans MongoDB.
 *
 * C’est quoi un journal d'événements ?
 * - C’est comme un carnet de bord.
 * - À chaque action importante (ex : covoiturage créé, participation confirmée, avis modéré),
 *   j’écris une ligne (un document) dans MongoDB.
 *
 * Pourquoi je fais ça en MongoDB et pas en PostgreSQL ?
 * - Parce que ce journal n’est pas une table "métier" principale.
 * - Je veux juste garder des traces, sans ajouter plein de tables et de relations.
 * - MongoDB est pratique pour stocker des événements qui peuvent avoir des "données" variables.
 *
 * Important :
 * - Ce fichier ne décide pas des règles du covoiturage.
 * - Il n'applique pas le métier.
 * - Il écrit juste des événements, point.
 */
final class JournalEvenementsMongodb
{
    /*
     * $collection = l’endroit précis dans MongoDB où je vais écrire.
     *
     * MongoDB = serveur
     * base (database) = dossier
     * collection = "table" version MongoDB (mais plus souple)
     */
    private Collection $collection;

    /*
     * Ici je prépare la connexion vers MongoDB.
     *
     * Les valeurs par défaut correspondent à Docker :
     * - mongodb:27017 -> nom du service + port (dans docker-compose)
     * - ecoride_journal -> nom de la base MongoDB (database)
     * - journal_evenements -> nom de la collection
     *
     * Si un jour je change l'installation (serveur, autre nom),
     * je pourrai remplacer ces valeurs par des variables d’environnement.
     */
    public function __construct(
        string $uri = 'mongodb://mongodb:27017',
        string $base = 'ecoride_journal',
        string $nom_collection = 'journal_evenements'
    ) {
        try {
            // Client MongoDB = l'objet qui permet de parler à MongoDB
            $client = new Client($uri);

            // Je "vise" la bonne base + la bonne collection
            $this->collection = $client->selectCollection($base, $nom_collection);
        } catch (Throwable $e) {
            /*
             * Si MongoDB n'est pas disponible (container éteint, mauvais nom, mauvais port),
             * je lance une erreur claire.
             *
             * (Je garde l’erreur d’origine dans $e pour débugger si besoin.)
             */
            throw new RuntimeException(
                "Impossible de se connecter à MongoDB pour écrire le journal d’événements.",
                0,
                $e
            );
        }
    }

    /*
     * Ajouter un événement dans MongoDB.
     *
     * $type_evenement :
     * - c’est une étiquette courte qui décrit ce qui s’est passé
     *   ex : "covoiturage_cree"
     *
     * $donnees :
     * - ce sont les infos utiles liées à cet événement
     *   ex : ['id_covoiturage' => 12, 'id_utilisateur' => 3]
     *
     * Pourquoi je mets $donnees dans un tableau ?
     * - Parce que selon l’événement, je n’aurai pas toujours les mêmes infos.
     *   (et MongoDB accepte très bien ça)
     */
    public function ajouter(string $type_evenement, array $donnees): void
    {
        $this->collection->insertOne([
            'type_evenement' => $type_evenement,

            /*
             * Je stocke une date en UTC, au format ISO 8601.
             *
             * Exemple : "2026-02-05T11:03:00+00:00"
             *
             * Pourquoi c’est pratique ?
             * - Ça se trie bien (du plus ancien au plus récent).
             * - C’est lisible quand j’ouvre MongoDB pour vérifier.
             */
            'cree_le_utc' => gmdate('c'),

            // Les infos liées à l’événement
            'donnees' => $donnees,
        ]);
    }
}
