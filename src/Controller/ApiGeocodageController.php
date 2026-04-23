<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GeocodageAdresse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur API du géocodage d'adresse.
 *
 * Cette classe gère un point d'entrée HTTP utilisé par l'interface
 * quand l'utilisateur commence à saisir une adresse.
 *
 * Le rôle du contrôleur reste centré sur le web :
 * lire les paramètres transmis dans l'URL,
 * encadrer les valeurs reçues,
 * puis renvoyer une réponse JSON exploitable côté interface.
 *
 * La recherche réelle des suggestions d'adresses
 * est déléguée au service GeocodageAdresse.
 */
final class ApiGeocodageController extends AbstractController
{
    /**
     * Renvoie des suggestions d'adresses au format JSON.
     *
     * Cette route fonctionne en GET.
     * Elle attend principalement deux paramètres :
     * - q : le texte saisi par l'utilisateur ;
     * - limite : le nombre maximum de suggestions à renvoyer.
     *
     * La méthode commence par nettoyer la saisie,
     * puis encadre la limite entre 1 et 10
     * afin d'éviter des valeurs incohérentes.
     *
     * Une garde est aussi appliquée sur la longueur de la recherche :
     * si la chaîne contient moins de 3 caractères,
     * la méthode renvoie immédiatement un tableau vide.
     * Cela évite de lancer une recherche trop courte,
     * souvent peu utile et plus coûteuse.
     *
     * Si la saisie est exploitable,
     * le contrôleur appelle ensuite le service GeocodageAdresse
     * puis renvoie les résultats dans une réponse JSON.
     *
     * @param Request $requete Requête HTTP courante.
     * @param GeocodageAdresse $geocodage Service chargé de rechercher les suggestions.
     *
     * @return JsonResponse Réponse JSON contenant la liste des suggestions.
     */
    #[Route('/api/geocodage/adresse', name: 'api_geocodage_adresse', methods: ['GET'])]
    public function adresse(Request $requete, GeocodageAdresse $geocodage): JsonResponse
    {
        /*
         * On lit la chaîne de recherche transmise dans l'URL
         * avec le paramètre "q", puis on supprime les espaces inutiles.
         */
        $q = trim((string) $requete->query->get('q', ''));

        /*
         * On lit aussi la limite demandée.
         * Si elle n'est pas fournie, la valeur 5 est utilisée par défaut.
         */
        $limite = (int) $requete->query->get('limite', 5);

        /*
         * On impose une borne minimale à 1
         * pour éviter une limite nulle ou négative.
         */
        if ($limite < 1) {
            $limite = 1;
        }

        /*
         * On impose aussi une borne maximale à 10
         * pour garder une réponse courte et exploitable.
         */
        if ($limite > 10) {
            $limite = 10;
        }

        /*
         * Une recherche trop courte n'est pas envoyée au service.
         * En dessous de 3 caractères, on renvoie directement
         * une structure JSON vide mais valide.
         */
        if (mb_strlen($q) < 3) {
            return $this->json(['suggestions' => []]);
        }

        /*
         * Si la saisie est suffisante,
         * le contrôleur délègue la recherche au service GeocodageAdresse
         * puis renvoie les suggestions trouvées au format JSON.
         */
        return $this->json([
            'suggestions' => $geocodage->chercher($q, $limite),
        ]);
    }
}