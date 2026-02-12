<?php

declare(strict_types=1);

namespace App\Service;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

final class JournalEvenements
{
    private Client $client;
    private string $nomBase;

    public function __construct(string $mongoUri, string $nomBase)
    {
        $this->client = new Client($mongoUri);
        $this->nomBase = $nomBase; // ex : "ecoride_journal"
    }

    public function enregistrer(
        string $typeEvenement,
        string $entite,
        int $idEntite,
        array $donnees = [],
    ): string {
        $collection = $this->client->selectCollection($this->nomBase, 'journal_evenements');

        $document = [
            'type_evenement' => $typeEvenement,
            'entite' => $entite,
            'id_entite' => $idEntite,
            'donnees' => $donnees,
            'cree_le' => new UTCDateTime(),
        ];

        $resultat = $collection->insertOne($document);

        return (string) $resultat->getInsertedId();
    }
}
