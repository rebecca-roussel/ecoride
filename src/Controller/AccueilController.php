<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page d'accueil.
 *
 * Cette classe gère le point d'entrée principal public de l'application.
 * Son rôle reste volontairement simple :
 * associer l'URL d'accueil à une réponse HTML rendue par Twig.
 *
 * Dans ce contrôleur, aucune lecture en base de données
 * et aucune règle métier particulière ne sont nécessaires.
 * Le traitement consiste uniquement à renvoyer la vue
 * correspondant à la page d'accueil.
 */
final class AccueilController extends AbstractController
{
    /**
     * Affiche la page d'accueil du site.
     *
     * La route "/" correspond au chemin de la racine de l'URL.
     * Dans EcoRide, elle sert à viser la page d'accueil.
     *
     * La méthode utilise render() pour demander à Symfony
     * de générer une réponse HTML à partir du template Twig
     * "accueil/index.html.twig".
     *
     * @return Response Réponse HTTP contenant le rendu HTML de la page d'accueil.
     */
    #[Route('/', name: 'accueil')]
    public function __invoke(): Response
    {
        /*
         * On renvoie directement la vue Twig de l'accueil.
         *
         * Symfony transforme ce template en contenu HTML,
         * puis l'encapsule dans un objet Response
         * qui sera envoyé au navigateur.
         */
        return $this->render('accueil/index.html.twig');
    }
}