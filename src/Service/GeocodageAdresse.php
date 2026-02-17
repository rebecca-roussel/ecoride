<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class GeocodageAdresse
{
    /*
      PLAN (GeocodageAdresse) :

      1) Objectif
         - interroger le service de géocodage de la Géoplateforme (IGN)
         - retourner une liste de suggestions utilisables dans un formulaire

      2) Stratégie
         - endpoint : https://data.geopf.fr/geocodage/search  (géocodage direct)
         - on demande du JSON et on extrait :
           * libellé (properties.label / properties.name)
           * score (properties.score)
           * coordonnées (geometry.coordinates) -> [longitude, latitude]

      3) Robustesse
         - si requête vide ou trop courte : []
         - si le service externe tombe : []
         - aucune exception ne doit remonter jusqu’à Twig
    */

    private const URL_RECHERCHE = 'https://data.geopf.fr/geocodage/search';

    public function __construct(
        private HttpClientInterface $http,
    ) {
    }

    /**
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
        $q = trim($q);

        if (mb_strlen($q) < 3) {
            return [];
        }

        if ($limite < 1) {
            $limite = 1;
        }
        if ($limite > 10) {
            $limite = 10;
        }

        try {
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

            $donnees = $reponse->toArray(false);

            if (!is_array($donnees) || !isset($donnees['features']) || !is_array($donnees['features'])) {
                return [];
            }

            $suggestions = [];

            foreach ($donnees['features'] as $feature) {
                if (!is_array($feature)) {
                    continue;
                }

                $props = isset($feature['properties']) && is_array($feature['properties'])
                    ? $feature['properties']
                    : [];

                $geo = isset($feature['geometry']) && is_array($feature['geometry'])
                    ? $feature['geometry']
                    : [];

                $coords = isset($geo['coordinates']) && is_array($geo['coordinates'])
                    ? $geo['coordinates']
                    : null;


                if (!is_array($coords) || count($coords) < 2) {
                    continue;
                }

                $longitude = (float) $coords[0];
                $latitude  = (float) $coords[1];

                $libelle = '';
                if (isset($props['label']) && is_string($props['label'])) {
                    $libelle = trim($props['label']);
                } elseif (isset($props['name']) && is_string($props['name'])) {
                    $libelle = trim($props['name']);
                }

                if ($libelle === '') {
                    continue;
                }

                $score = null;
                if (isset($props['score']) && (is_float($props['score']) || is_int($props['score']))) {
                    $score = (float) $props['score'];
                }

                $type = null;
                if (isset($props['type']) && is_string($props['type'])) {
                    $type = $props['type'];
                }

                $suggestions[] = [
                    'libelle' => $libelle,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'score' => $score,
                    'type' => $type,
                ];
            }

            return $suggestions;
        } catch (Throwable) {
            return [];
        }
    }
}

