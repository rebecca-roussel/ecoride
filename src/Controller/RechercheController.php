<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page de recherche.
 *
 * Cette classe gère l'affichage du formulaire de recherche
 * des covoiturages.
 *
 * Son rôle reste centré sur la requête HTTP :
 * lire les critères transmis dans l'URL,
 * les préparer pour la vue,
 * puis renvoyer la page Twig correspondante.
 *
 * Ce contrôleur n'exécute pas lui-même la recherche.
 * Il prépare seulement les valeurs utiles à l'affichage
 * du formulaire et à la réaffichage des saisies utilisateur.
 */
final class RechercheController extends AbstractController
{
    /**
     * Affiche la page de recherche avec les critères déjà saisis.
     *
     * La route /recherche permet d'ouvrir le formulaire
     * de recherche des covoiturages.
     *
     * Les paramètres sont lus dans la chaîne de requête,
     * puis transmis à Twig dans un tableau "criteres".
     * Cela permet de réafficher les valeurs déjà saisies
     * si l'utilisateur revient sur la page ou si un message
     * doit être affiché.
     *
     * Les critères pris en compte ici sont :
     * - la ville de départ ;
     * - la ville d'arrivée ;
     * - la date ;
     * - l'heure minimale ;
     * - l'heure maximale.
     *
     * Un message d'erreur éventuel est aussi transmis à la vue
     * pour permettre son affichage dans l'interface.
     *
     * @param Request $requete Requête HTTP courante.
     *
     * @return Response Réponse HTTP contenant le rendu HTML de la page de recherche.
     */
    #[Route('/recherche', name: 'recherche', methods: ['GET'])]
    public function __invoke(Request $requete): Response
    {
        /*
         * On lit les critères transmis dans l'URL.
         *
         * Chaque valeur est convertie en chaîne de caractères
         * pour garantir un format exploitable dans Twig,
         * même si le paramètre n'existe pas.
         *
         * Une chaîne vide est utilisée par défaut
         * quand aucun critère n'est fourni.
         */
        $criteres = [
            'ville_depart' => (string) $requete->query->get('ville_depart', ''),
            'ville_arrivee' => (string) $requete->query->get('ville_arrivee', ''),
            'date' => (string) $requete->query->get('date', ''),
            'heure_min' => (string) $requete->query->get('heure_min', ''),
            'heure_max' => (string) $requete->query->get('heure_max', ''),
        ];

        /*
         * On lit aussi un éventuel message d'erreur
         * transmis dans l'URL.
         *
         * Cette valeur permet à la vue d'afficher
         * un retour lisible à l'utilisateur
         * sans perdre les critères déjà saisis.
         */
        $messageErreur = (string) $requete->query->get('message_erreur', '');

        /*
         * On renvoie la vue Twig de la page de recherche
         * avec les critères et le message éventuel.
         *
         * Twig pourra ainsi préremplir le formulaire
         * et afficher le message si nécessaire.
         */
        return $this->render('recherche/index.html.twig', [
            'criteres' => $criteres,
            'message_erreur' => $messageErreur,
        ]);
    }
}