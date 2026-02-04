<?php

declare(strict_types=1);

namespace App\Infra;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\BSON\UTCDateTime;

final class JournalEvenementsMongo
{
    private Collection $collection;

    public function __construct()
    {
        $mongoUrl = $_ENV['MONGODB_URL'] ?? getenv('MONGODB_URL') ?: 'mongodb://mongodb:27017';

        $client = new Client($mongoUrl);

        $this->collection = $client
            ->selectDatabase('ecoride_journal')
            ->selectCollection('journal_evenements');
    }

    public function journaliserEvenement(
        string $typeEvenement,
        string $entite,
        ?int $idEntite,
        array $donnees
    ): void {
        $this->collection->insertOne([
            'type_evenement' => $typeEvenement,
            'entite' => $entite,
            'id_entite' => $idEntite,
            'donnees' => $donnees,
            'cree_le' => new UTCDateTime(),
        ]);
    }
}
