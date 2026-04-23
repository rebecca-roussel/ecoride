<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page de contact.
 *
 * Cette classe gère l'affichage de la page de contact publique.
 * Son rôle reste simple :
 * associer l'URL /contact à une réponse HTML rendue par Twig.
 *
 * Dans ce contrôleur, aucun traitement métier
 * et aucun accès aux données ne sont nécessaires.
 * La méthode se contente de renvoyer la vue attendue.
 */
final class ContactController extends AbstractController
{
    /**
     * Affiche la page de contact.
     *
     * La route /contact est accessible en GET.
     * Elle permet d'ouvrir la page de contact du site.
     *
     * La méthode utilise render() pour demander à Symfony
     * de générer une réponse HTML à partir du template Twig
     * "contact/index.html.twig".
     *
     * @return Response Réponse HTTP contenant le rendu HTML de la page de contact.
     */
    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function index(): Response
    {
        /*
         * On renvoie directement la vue Twig de la page contact.
         *
         * Symfony transforme le template en HTML,
         * puis l'encapsule dans un objet Response
         * qui sera renvoyé au navigateur.
         */
        return $this->render('contact/index.html.twig');
    }
}