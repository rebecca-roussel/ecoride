<?php
declare(strict_types=1);

namespace App\Infrastructure;

use MongoDB\Client;
use MongoDB\Collection;
use RuntimeException;
use Throwable;

final class JournalEvenementsMongodb
{
    private Collection $collection;

    /*
     * Ici je prépare l'accès à MongoDB.
     * Je ne fais pas de logique métier : je me contente d'écrire un événement.
     *
     * Je passe les infos de connexion en paramètres pour rester simple et clair.
     * Plus tard, Symfony pourra fournir ces valeurs via la configuration (env).
     */
    public function __construct(
        string $uri = 'mongodb://mongodb:27017',
        string $base = 'ecoride_journal',
        string $nom_collection = 'journal_evenements'
    ) {
        try {
            $client = new Client($uri);
            $this->collection = $client->selectCollection($base, $nom_collection);
        } catch (Throwable $e) {
            // Je renvoie une erreur claire si MongoDB n'est pas accessible
            throw new RuntimeException("Impossible de se connecter à MongoDB pour journaliser.", 0, $e);
        }
    }

    /*
     * Ajoute un événement dans le journal MongoDB.
     * - $type_evenement : ex 'covoiturage_cree'
     * - $donnees : ex ['id_covoiturage' => 12, 'id_utilisateur' => 3, ...]
     */
    public function ajouter(string $type_evenement, array $donnees): void
    {
        $this->collection->insertOne([
            'type_evenement' => $type_evenement,
            // date UTC au format ISO 8601 (pratique pour trier)
            'cree_le_utc' => gmdate('c'),
            'donnees' => $donnees,
        ]);
    }
}
