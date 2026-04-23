<?php

declare(strict_types=1);

namespace App\Service;

use MongoDB\Client;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service de journalisation des événements applicatifs dans MongoDB.
 *
 * Cette classe sert à enregistrer une trace chronologique
 * de certains événements importants du projet EcoRide.
 *
 * Répartition des rôles techniques :
 * - PostgreSQL reste la base relationnelle du projet
 *   pour les données métier structurées ;
 * - MongoDB est utilisé ici comme journal d'événements ;
 * - Symfony Monolog sert de secours si l'écriture MongoDB échoue.
 *
 * Le principe retenu est simple :
 * on ajoute des événements dans le journal,
 * mais on ne réécrit pas l'historique.
 *
 * Règle importante :
 * la journalisation ne doit jamais casser l'application.
 * Si MongoDB devient indisponible,
 * le service renvoie une chaîne vide
 * et écrit une trace d'erreur dans les logs Symfony.
 */
final class JournalEvenements
{
    /**
     * Client MongoDB utilisé pour accéder à la base de journalisation.
     */
    private Client $client;

    /**
     * Nom de la base MongoDB utilisée pour le journal.
     */
    private string $nomBase;

    /**
     * Initialise le service de journalisation.
     *
     * Le constructeur prépare le client MongoDB
     * et mémorise le nom de la base à utiliser.
     *
     * @param string $mongoUri URI de connexion MongoDB.
     * @param string $nomBase Nom de la base MongoDB du journal.
     * @param LoggerInterface $logger Service de logs Symfony utilisé en secours.
     */
    public function __construct(
        string $mongoUri,
        string $nomBase,
        private LoggerInterface $logger,
    ) {
        /*
         * On instancie le client MongoDB à partir de l'URI fournie.
         * Ce client servira ensuite à sélectionner la collection
         * du journal d'événements.
         */
        $this->client = new Client($mongoUri);

        /*
         * On mémorise le nom de la base MongoDB
         * pour éviter de le répéter ailleurs dans la classe.
         */
        $this->nomBase = $nomBase;
    }

    /**
     * Enregistre un événement dans la collection "journal_evenements".
     *
     * Chaque événement contient :
     * - un type d'événement ;
     * - une entité concernée ;
     * - un identifiant d'entité ;
     * - un tableau de données contextuelles ;
     * - une date de création en UTC.
     *
     * Garde-fous appliqués :
     * - le type d'événement ne doit pas être vide ;
     * - l'entité ne doit pas être vide ;
     * - l'identifiant d'entité doit être strictement positif.
     *
     * Si ces conditions ne sont pas respectées,
     * l'événement n'est pas inséré
     * et une alerte est envoyée dans les logs Symfony.
     *
     * Si l'insertion MongoDB réussit,
     * la méthode renvoie l'identifiant du document créé.
     *
     * Si MongoDB ne répond pas
     * ou si une exception survient,
     * la méthode écrit une erreur dans les logs Symfony
     * puis renvoie une chaîne vide.
     *
     * @param string $typeEvenement Type d'événement à enregistrer.
     * @param string $entite Entité concernée par l'événement.
     * @param int $idEntite Identifiant de l'entité concernée.
     * @param array $donnees Contexte complémentaire de l'événement.
     *
     * @return string Identifiant MongoDB du document créé, ou chaîne vide en cas d'échec.
     */
    public function enregistrer(
        string $typeEvenement,
        string $entite,
        int $idEntite,
        array $donnees = []
    ): string {
        /*
         * On nettoie les champs textuels principaux
         * pour éviter des valeurs composées uniquement d'espaces.
         */
        $typeEvenement = trim($typeEvenement);
        $entite = trim($entite);

        /*
         * On applique des garde-fous simples
         * avant de tenter l'insertion MongoDB.
         *
         * Si le type, l'entité ou l'identifiant sont invalides,
         * l'événement est ignoré
         * et une alerte est tracée dans les logs Symfony.
         */
        if ($typeEvenement === '' || $entite === '' || $idEntite <= 0) {
            $this->logger->warning('JournalEvenements : événement ignoré (données invalides).', [
                'type_evenement' => $typeEvenement,
                'entite' => $entite,
                'id_entite' => $idEntite,
            ]);

            return '';
        }

        try {
            /*
             * On récupère la collection MongoDB
             * qui servira à stocker les événements.
             */
            $collection = $this->obtenirCollection();

            /*
             * Construction du document MongoDB.
             *
             * Les clés sont écrites en snake_case
             * pour garder un format homogène.
             *
             * cree_le est enregistré en UTC
             * avec le type MongoDB prévu pour la date.
             */
            $document = [
                'type_evenement' => $typeEvenement,
                'entite' => $entite,
                'id_entite' => $idEntite,
                'donnees' => $donnees,
                'cree_le' => new \MongoDB\BSON\UTCDateTime(),
            ];

            /*
             * Insertion du document dans MongoDB.
             */
            $resultat = $collection->insertOne($document);

            /*
             * Si l'insertion réussit,
             * on renvoie l'identifiant Mongo du document créé.
             */
            return (string) $resultat->getInsertedId();
        } catch (Throwable $exception) {
            /*
             * Si MongoDB devient indisponible
             * ou si une erreur survient pendant l'insertion,
             * on n'interrompt pas le reste de l'application.
             *
             * À la place, on garde une trace dans les logs Symfony
             * pour conserver un minimum de diagnostic.
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

    /**
     * Alias plus lisible pour enregistrer un événement.
     *
     * Cette méthode évite d'exposer partout
     * le nom interne "enregistrer" si le code appelant
     * préfère un intitulé plus explicite.
     *
     * Elle délègue entièrement le travail
     * à la méthode enregistrer().
     *
     * @param string $typeEvenement Type d'événement à enregistrer.
     * @param string $entite Entité concernée.
     * @param int $idEntite Identifiant de l'entité concernée.
     * @param array $donnees Contexte complémentaire.
     *
     * @return string Identifiant MongoDB du document créé, ou chaîne vide en cas d'échec.
     */
    public function enregistrerEvenement(
        string $typeEvenement,
        string $entite,
        int $idEntite,
        array $donnees = []
    ): string {
        /*
         * On délègue directement à la méthode principale
         * de journalisation.
         */
        return $this->enregistrer($typeEvenement, $entite, $idEntite, $donnees);
    }

    /**
     * Enregistre une erreur dans le journal d'événements.
     *
     * Cette méthode sert à uniformiser la structure
     * des erreurs enregistrées dans MongoDB.
     *
     * Les informations stockées comprennent :
     * - le message ;
     * - la classe de l'exception ;
     * - le code ;
     * - le fichier ;
     * - la ligne ;
     * - un contexte supplémentaire éventuel.
     *
     * @param string $typeEvenement Type d'événement utilisé pour l'erreur.
     * @param string $entite Entité concernée.
     * @param int $idEntite Identifiant de l'entité concernée.
     * @param Throwable $erreur Exception ou erreur capturée.
     * @param array $contexte Contexte complémentaire utile au diagnostic.
     *
     * @return string Identifiant MongoDB du document créé, ou chaîne vide en cas d'échec.
     */
    public function enregistrerErreur(
        string $typeEvenement,
        string $entite,
        int $idEntite,
        Throwable $erreur,
        array $contexte = []
    ): string {
        /*
         * On construit un tableau homogène
         * qui décrit l'erreur technique capturée.
         */
        $donnees = [
            'message' => $erreur->getMessage(),
            'classe' => $erreur::class,
            'code' => $erreur->getCode(),
            'fichier' => $erreur->getFile(),
            'ligne' => $erreur->getLine(),
            'contexte' => $contexte,
        ];

        /*
         * On réutilise ensuite la méthode principale
         * pour écrire ce document dans le journal.
         */
        return $this->enregistrer($typeEvenement, $entite, $idEntite, $donnees);
    }

    /**
     * Retourne la collection MongoDB utilisée pour le journal.
     *
     * Cette méthode centralise la sélection de la collection
     * afin d'éviter de répéter le même appel ailleurs dans la classe.
     *
     * @return Collection Collection MongoDB "journal_evenements".
     */
    private function obtenirCollection(): Collection
    {
        /*
         * On sélectionne la collection du journal
         * dans la base MongoDB configurée pour l'application.
         */
        return $this->client->selectCollection($this->nomBase, 'journal_evenements');
    }
}
