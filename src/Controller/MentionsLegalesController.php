<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page des mentions légales.
 *
 * Cette classe gère l'affichage d'une page publique du site.
 * Son rôle consiste à associer l'URL des mentions légales
 * à une réponse HTML rendue par Twig.
 *
 * Aucun traitement métier particulier
 * et aucun accès aux données ne sont nécessaires ici.
 * La méthode renvoie simplement la vue attendue.
 */
final class MentionsLegalesController extends AbstractController
{
    /**
     * Affiche la page des mentions légales.
     *
     * La route /mentions-legales permet d'ouvrir
     * la page légale publique du site.
     *
     * La méthode utilise render() pour demander à Symfony
     * de générer une réponse HTML à partir du template Twig
     * "mentions_legales/index.html.twig".
     *
     * @return Response Réponse HTTP contenant le rendu HTML de la page des mentions légales.
     */
    #[Route('/mentions-legales', name: 'mentions_legales', methods: ['GET'])]
    public function index(): Response
    {
        /*
         * On renvoie directement la vue Twig
         * correspondant aux mentions légales.
         *
         * Symfony transforme le template en HTML,
         * puis l'encapsule dans un objet Response
         * qui sera envoyé au navigateur.
         */
        return $this->render('mentions_legales/index.html.twig');
    }
}