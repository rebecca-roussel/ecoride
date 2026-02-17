<?php

declare(strict_types=1);

namespace App\Service;

use MongoDB\Client;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

final class JournalEvenements
{
    /*
      PLAN (JournalEvenements) :

      1) Pourquoi on a ce service
         - PostgreSQL stocke les données 
         - MongoDB sert de journal d’événements 
         - on ajoute des événements, on ne réécrit pas le passé

      2) Ce que je stocke dans un événement
         - type_evenement : ce qui s’est passé
         - entite : sur quoi ça s’applique (utilisateur, voiture, covoiturage, etc.)
         - id_entite : identifiant concerné 
         - donnees : contexte utile 
         - cree_le : date/heure de création en UTC

      3) Règle importante
         - le journal ne doit jamais casser l’application
         - si MongoDB est indisponible, je continue quand même

      4) Bonus utile
         - si MongoDB est KO : je ne perds pas tout, je trace dans les logs Symfony
         - garde-fous : pas d’événements vides 
    */

    private Client $client;
    private string $nomBase;

    /*
      Constructeur : connexion Mongo + accès aux logs

      - $mongoUri : URI MongoDB ( mongodb://mongodb:27017)
      - $nomBase : nom de la base ( ecoride_journal)
      - $logger : logger Symfony (fallback si MongoDB ne répond pas)
    */
    public function __construct(
        string $mongoUri,
        string $nomBase,
        private LoggerInterface $logger,
    ) {
        $this->client = new Client($mongoUri);
        $this->nomBase = $nomBase;
    }

    /*
      Enregistre un événement dans la collection "journal_evenements"

      Retour :
      - id Mongo du document inséré (string)
      - ou chaîne vide '' si on ne peut pas journaliser 
    */
    public function enregistrer(
        string $typeEvenement,
        string $entite,
        int $idEntite,
        array $donnees = []
    ): string {
        /* Garde-fous pour éviter des événements vides */
        $typeEvenement = trim($typeEvenement);
        $entite = trim($entite);

        if ($typeEvenement === '' || $entite === '' || $idEntite <= 0) {
            $this->logger->warning('JournalEvenements : événement ignoré (données invalides).', [
                'type_evenement' => $typeEvenement,
                'entite' => $entite,
                'id_entite' => $idEntite,
            ]);

            return '';
        }

        try {
            // Récupère la collection (base choisie + collection "journal_evenements")
            $collection = $this->obtenirCollection();

            /*
              Document Mongo
              - clefs en snake_case 
              - cree_le en UTC 
              - donnees : contexte libre (optionnel)
            */
            $document = [
                'type_evenement' => $typeEvenement,
                'entite' => $entite,
                'id_entite' => $idEntite,
                'donnees' => $donnees,
                'cree_le' => new \MongoDB\BSON\UTCDateTime(),
            ];

            // Insertion en MongoDB
            $resultat = $collection->insertOne($document);

            // Je renvoie l'identifiant du document créé
            return (string) $resultat->getInsertedId();
        } catch (Throwable $exception) {
            /*
              Fallback
              - si MongoDB est KO je ne casse pas l’application
              - mais je ne veux pas être aveugle donc je trace dans les logs Symfony
            */
            $this->logger->error('JournalEvenements : insertion MongoDB impossible.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'type_evenement' => $typeEvenement,
                'entite' => $entite,
                'id_entite' => $idEntite,
            ]);

            return '';
        }
    }

    /*
      Alias plus lisible pour les contrôleurs
      - côté contrôleur, on veux un nom explicite
      - je garde enregistrer() comme moteur interne
    */
    public function enregistrerEvenement(
        string $typeEvenement,
        string $entite,
        int $idEntite,
        array $donnees = []
    ): string {
        return $this->enregistrer($typeEvenement, $entite, $idEntite, $donnees);
    }

    /*
      Enregistre une erreur dans le journal
      - format homogène
      - ne pas afficher les détails techniques à l’utilisateur
      - mais garder de quoi diagnostiquer (message, fichier, ligne, etc.)
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

    /*
      Petite méthode privée source unique de vérité pour la collection
      - évite de répéter selectCollection partout
      - si demain je change le nom de collection, je change ici 
    */
    private function obtenirCollection(): Collection
    {
        return $this->client->selectCollection($this->nomBase, 'journal_evenements');
    }
}

