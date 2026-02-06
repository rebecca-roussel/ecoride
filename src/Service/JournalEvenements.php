<?php

namespace App\Service;

use MongoDB\Client;

final class JournalEvenements
{
    private Client $client;
    private string $dbName;

    public function __construct(string $mongoUri, string $dbName)
    {
        $this->client = new Client($mongoUri);
        $this->dbName = $dbName;
    }

    public function enregistrer(string $type, array $donnees = []): string
    {
        $collection = $this->client->selectCollection($this->dbName, 'evenements');

        $document = [
            'type' => $type,
            'donnees' => $donnees,
            'date_utc' => new \MongoDB\BSON\UTCDateTime((int) (microtime(true) * 1000)),
        ];

        $resultat = $collection->insertOne($document);

        return (string) $resultat->getInsertedId();
    }
}

