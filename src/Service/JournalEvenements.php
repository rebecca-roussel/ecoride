<?php

declare(strict_types=1);

namespace App\Service;

use MongoDB\Client;
use Throwable;

final class JournalEvenements
{
    /*
      PLAN (JournalEvenements) :

      1) Pourquoi on a ce service
         - PostgreSQL stocke les données “métier”
         - MongoDB sert de journal d’événements (append-only)
         - on ajoute des événements, on ne réécrit pas le passé

      2) Ce que je stocke dans un événement
         - type_evenement : ce qui s’est passé
         - entite : sur quoi ça s’applique (utilisateur, voiture, covoiturage, etc.)
         - id_entite : identifiant concerné (ex : id_utilisateur)
         - donnees : contexte utile (facultatif)
         - cree_le : date/heure de création en UTC

      3) Objectif “pro” important
         - le journal ne doit jamais casser l’application
         - si MongoDB est indisponible, je continue quand même
           et je renvoie une valeur neutre

      4) Aide pour les erreurs
         - je veux une méthode simple pour journaliser une erreur
         - je stocke message, classe, code, fichier, ligne + contexte
    */

    private Client $client;
    private string $nomBase;

    public function __construct(string $mongoUri, string $nomBase)
    {
        /*
          Connexion MongoDB
          - $mongoUri vient de la config
          - $nomBase est le nom de la base Mongo
        */
        $this->client = new Client($mongoUri);
        $this->nomBase = $nomBase;
    }

    /*
      Enregistre un événement dans la collection "journal_evenements"

      - je renvoie l'id Mongo du document inséré
      - si MongoDB ne répond pas, je renvoie une chaîne vide
        (but : ne pas bloquer le site)
    */
    public function enregistrer(
        string $typeEvenement,
        string $entite,
        int $idEntite,
        array $donnees = []
    ): string {
        try {
            // Je cible la collection dans la base Mongo choisie
            $collection = $this->client->selectCollection($this->nomBase, 'journal_evenements');

            /*
              Je construis le document Mongo
              - clefs en snake_case pour rester cohérente avec le projet
              - cree_le en UTC pour éviter les soucis de fuseau horaire
            */
            $document = [
                'type_evenement' => $typeEvenement,
                'entite' => $entite,
                'id_entite' => $idEntite,
                'donnees' => $donnees,
                'cree_le' => new \MongoDB\BSON\UTCDateTime(),

            ];

            // Insertion en base Mongo
            $resultat = $collection->insertOne($document);

            // Je renvoie l'identifiant du document créé
            return (string) $resultat->getInsertedId();
        } catch (Throwable) {
            /*
              Très important :
              - si MongoDB est KO, je ne casse pas l’application
              - je renvoie une valeur neutre
            */
            return '';
        }
    }

    /*
      Enregistre une erreur dans le journal.

      But :
      - avoir un format identique pour toutes les erreurs
      - garder un maximum d’informations utiles sans les afficher à l’utilisateur
    */
    public function enregistrerErreur(
        string $typeEvenement,
        string $entite,
        int $idEntite,
        Throwable $erreur,
        array $contexte = []
    ): string {
        $donnees = [
            'message' => $erreur->getMessage(),
            'classe' => $erreur::class,
            'code' => $erreur->getCode(),
            'fichier' => $erreur->getFile(),
            'ligne' => $erreur->getLine(),
            'contexte' => $contexte,
        ];

        return $this->enregistrer($typeEvenement, $entite, $idEntite, $donnees);
    }
}

