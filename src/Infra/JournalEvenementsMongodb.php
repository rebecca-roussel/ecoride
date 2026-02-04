<?php
declare(strict_types=1);

namespace App\Infra;

use MongoDB\Client;
use MongoDB\Collection;
use RuntimeException;

final class JournalEvenementsMongodb
{
    private Collection $collection;

    public function __construct()
    {
        $uri = $_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017';
        $base = $_ENV['MONGO_DB'] ?? 'ecoride_journal';
        $collection = $_ENV['MONGO_COLLECTION'] ?? 'journal_evenements';

        if (!class_exists(Client::class)) {
            throw new RuntimeException("MongoDB PHP non disponible. Vérifier extension + dépendance composer.");
        }

        $client = new Client($uri);
        $this->collection = $client->selectCollection($base, $collection);
    }

    public function ajouter(string $type_evenement, array $donnees): void
    {
        $this->collection->insertOne([
            'type_evenement' => $type_evenement,
            'cree_le_utc' => gmdate('c'),
            'donnees' => $donnees,
        ]);
    }
}
