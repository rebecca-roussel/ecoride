<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Service de géocodage d'adresse.
 *
 * Cette classe interroge l'API de géocodage de la GéoPlateforme
 * afin de récupérer des suggestions d'adresses à partir d'un texte saisi.
 *
 * Son rôle est de :
 * - envoyer une requête HTTP vers l'API externe ;
 * - lire la réponse JSON renvoyée ;
 * - filtrer les données utiles ;
 * - reformater les suggestions dans une structure simple
 *   directement exploitable par le reste de l'application.
 *
 * Ce service reste volontairement prudent :
 * si la recherche est trop courte ou si l'appel échoue,
 * il renvoie simplement un tableau vide.
 */
final class GeocodageAdresse
{
    /**
     * URL du point d'entrée de recherche de l'API GéoPlateforme.
     */
    private const URL_RECHERCHE = 'https://data.geopf.fr/geocodage/search';

    /**
     * Initialise le service avec le client HTTP Symfony.
     *
     * @param HttpClientInterface $http Service chargé d'envoyer les requêtes HTTP.
     */
    public function __construct(
        private HttpClientInterface $http,
    ) {
    }

    /**
     * Recherche des suggestions d'adresses à partir d'un texte libre.
     *
     * La méthode envoie une requête GET à l'API de géocodage
     * avec le texte saisi et une limite de résultats.
     *
     * Elle applique d'abord plusieurs garde-fous :
     * - une recherche de moins de 3 caractères est ignorée ;
     * - la limite est encadrée entre 1 et 10.
     *
     * Si la réponse de l'API est valide,
     * la méthode parcourt les résultats,
     * extrait les informations utiles,
     * puis renvoie un tableau simplifié de suggestions.
     *
     * En cas d'erreur HTTP, d'erreur de structure
     * ou d'exception pendant le traitement,
     * la méthode renvoie un tableau vide.
     *
     * @param string $q Texte recherché par l'utilisateur.
     * @param int $limite Nombre maximum de suggestions souhaité.
     *
     * @return array<int, array{
     *   libelle: string,
     *   latitude: float,
     *   longitude: float,
     *   score: float|null,
     *   type: string|null
     * }>
     */
    public function chercher(string $q, int $limite = 5): array
    {
        /*
         * On nettoie la chaîne reçue
         * pour supprimer les espaces inutiles.
         */
        $q = trim($q);

        /*
         * Une recherche trop courte est ignorée.
         *
         * En dessous de 3 caractères,
         * les résultats sont souvent peu utiles
         * et le service renvoie directement un tableau vide.
         */
        if (mb_strlen($q) < 3) {
            return [];
        }

        /*
         * On encadre la limite minimale à 1
         * pour éviter une valeur nulle ou négative.
         */
        if ($limite < 1) {
            $limite = 1;
        }

        /*
         * On encadre aussi la limite maximale à 10
         * pour garder une réponse raisonnable.
         */
        if ($limite > 10) {
            $limite = 10;
        }

        try {
            /*
             * Envoi de la requête HTTP GET vers l'API externe.
             *
             * Les paramètres transmis dans l'URL sont :
             * - q : le texte recherché ;
             * - limit : le nombre maximum de résultats.
             *
             * Le header Accept demande une réponse JSON.
             * Le timeout de 3 secondes évite qu'un appel trop lent
             * bloque inutilement l'application.
             */
            $reponse = $this->http->request('GET', self::URL_RECHERCHE, [
                'query' => [
                    'q' => $q,
                    'limit' => $limite,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 3.0,
            ]);

            /*
             * Conversion de la réponse en tableau PHP.
             *
             * toArray(false) demande à Symfony
             * de transformer le JSON en tableau
             * sans lancer automatiquement d'exception HTTP.
             */
            $donnees = $reponse->toArray(false);

            /*
             * On vérifie que la structure générale attendue existe
             * et que la clé "features" contient bien un tableau.
             *
             * Si ce n'est pas le cas,
             * la méthode renvoie un tableau vide.
             */
            if (!is_array($donnees) || !isset($donnees['features']) || !is_array($donnees['features'])) {
                return [];
            }

            /*
             * Ce tableau contiendra les suggestions finales
             * reformattées pour l'application.
             */
            $suggestions = [];

            /*
             * Chaque "feature" représente une proposition
             * renvoyée par l'API.
             */
            foreach ($donnees['features'] as $feature) {
                /*
                 * On ignore les éléments mal formés.
                 */
                if (!is_array($feature)) {
                    continue;
                }

                /*
                 * Les propriétés descriptives sont stockées
                 * dans la clé "properties".
                 *
                 * Si elle manque ou n'a pas le bon type,
                 * on utilise un tableau vide.
                 */
                $props = isset($feature['properties']) && is_array($feature['properties'])
                    ? $feature['properties']
                    : [];

                /*
                 * Les informations géographiques sont stockées
                 * dans la clé "geometry".
                 *
                 * Là encore, si la structure n'est pas correcte,
                 * on retient un tableau vide.
                 */
                $geo = isset($feature['geometry']) && is_array($feature['geometry'])
                    ? $feature['geometry']
                    : [];

                /*
                 * Les coordonnées sont attendues
                 * dans "geometry.coordinates".
                 *
                 * Elles sont généralement fournies dans l'ordre :
                 * longitude, latitude.
                 */
                $coords = isset($geo['coordinates']) && is_array($geo['coordinates'])
                    ? $geo['coordinates']
                    : null;

                /*
                 * On ne garde que les résultats
                 * qui possèdent au moins deux coordonnées.
                 */
                if (!is_array($coords) || count($coords) < 2) {
                    continue;
                }

                /*
                 * On extrait les coordonnées sous forme de flottants.
                 *
                 * L'API renvoie d'abord la longitude,
                 * puis la latitude.
                 */
                $longitude = (float) $coords[0];
                $latitude = (float) $coords[1];

                /*
                 * Construction du libellé affiché dans les suggestions.
                 *
                 * On privilégie "label" si disponible,
                 * sinon on retombe sur "name".
                 */
                $libelle = '';

                if (isset($props['label']) && is_string($props['label'])) {
                    $libelle = trim($props['label']);
                } elseif (isset($props['name']) && is_string($props['name'])) {
                    $libelle = trim($props['name']);
                }

                /*
                 * Si aucun libellé exploitable n'est disponible,
                 * on ignore ce résultat.
                 */
                if ($libelle === '') {
                    continue;
                }

                /*
                 * Le score de pertinence est facultatif.
                 * S'il est présent et numérique,
                 * on le convertit en flottant.
                 */
                $score = null;
                if (isset($props['score']) && (is_float($props['score']) || is_int($props['score']))) {
                    $score = (float) $props['score'];
                }

                /*
                 * Le type du résultat est aussi facultatif.
                 * S'il existe et qu'il est bien textuel,
                 * on le conserve.
                 */
                $type = null;
                if (isset($props['type']) && is_string($props['type'])) {
                    $type = $props['type'];
                }

                /*
                 * On ajoute la suggestion reformattée
                 * dans la structure finale renvoyée par le service.
                 */
                $suggestions[] = [
                    'libelle' => $libelle,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'score' => $score,
                    'type' => $type,
                ];
            }

            /*
             * Si tout s'est bien passé,
             * on renvoie la liste finale des suggestions.
             */
            return $suggestions;
        } catch (Throwable) {
            /*
             * En cas d'erreur pendant l'appel HTTP
             * ou pendant le traitement de la réponse,
             * on renvoie simplement un tableau vide.
             *
             * Ce choix évite qu'un problème externe
             * casse le reste de l'application.
             */
            return [];
        }
    }
}