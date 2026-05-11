<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceCovoituragePostgresql;
use App\Service\SessionUtilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page de détail d'un covoiturage.
 *
 * Cette classe gère l'affichage d'une annonce précise.
 * Elle lit l'identifiant transmis dans l'URL,
 * récupère le détail du covoiturage dans PostgreSQL,
 * charge les avis validés du chauffeur,
 * puis envoie ces données au gabarit Twig.
 *
 * Le contrôleur garde ici son rôle web :
 * lire la requête HTTP,
 * vérifier que l'identifiant demandé est exploitable,
 * déclencher les lectures utiles
 * puis choisir la réponse HTML à renvoyer.
 *
 * Les accès aux données restent délégués
 * à `PersistanceCovoituragePostgresql`.
 */
final class DetailsController extends AbstractController
{
    /**
     * Affiche le détail d'un covoiturage.
     *
     * Le paramètre `id` est attendu dans l'URL,
     * par exemple sous la forme :
     * `/details?id=8`
     *
     * Si l'identifiant est absent ou invalide,
     * la méthode renvoie une page 404.
     *
     * Si le covoiturage existe,
     * le contrôleur récupère aussi les avis validés du chauffeur
     * afin d'alimenter la zone d'avis dans la page.
     *
     * L'identifiant de l'utilisateur connecté est également transmis au Twig.
     * Cela permet à la vue de savoir si la personne qui consulte la page
     * est le chauffeur du trajet,
     * par exemple pour masquer le bouton "Participer" sur son propre covoiturage.
     *
     * @param Request $requeteHttp Requête HTTP courante.
     * @param PersistanceCovoituragePostgresql $persistance
     *        Service de lecture PostgreSQL des covoiturages.
     * @param SessionUtilisateur $sessionUtilisateur
     *        Service qui permet de connaître l'utilisateur actuellement connecté.
     *
     * @return Response Réponse HTML rendue par Twig.
     */
    #[Route('/details', name: 'details', methods: ['GET'])]
    public function index(
        Request $requeteHttp,
        PersistanceCovoituragePostgresql $persistance,
        SessionUtilisateur $sessionUtilisateur
    ): Response {
        /*
         * L'identifiant est lu dans la chaîne de requête.
         * `getInt()` renvoie directement un entier.
         * Si le paramètre n'existe pas, on récupère 0 par défaut.
         */
        $id = $requeteHttp->query->getInt('id', 0);

        /*
         * Un identifiant nul ou négatif ne correspond pas
         * à un covoiturage exploitable.
         * On renvoie donc une 404.
         */
        if ($id <= 0) {
            throw $this->createNotFoundException('Identifiant de covoiturage invalide.');
        }

        /*
         * Lecture du détail complet du covoiturage.
         * Cette méthode récupère les informations utiles à l'affichage :
         * villes, dates, places, prix, chauffeur, voiture, préférences...
         */
        $detail = $persistance->obtenirDetailCovoiturageParId($id);

        /*
         * Si aucune ligne ne correspond à l'identifiant demandé,
         * la page n'a rien à afficher.
         */
        if ($detail === null) {
            throw $this->createNotFoundException('Covoiturage introuvable.');
        }

        /*
         * Par défaut, on prépare un tableau vide.
         * Il sera rempli seulement si un identifiant chauffeur existe.
         */
        $avisChauffeur = [];

        /*
         * Le détail du covoiturage contient l'identifiant du chauffeur.
         * On l'utilise ensuite pour aller chercher ses avis validés.
         */
        $idChauffeur = (int) ($detail['id_chauffeur'] ?? 0);

        if ($idChauffeur > 0) {
            /*
             * On limite volontairement à 5 avis
             * pour garder une page lisible.
             */
            $avisChauffeur = $persistance->obtenirAvisValidesDuChauffeur($idChauffeur, 5);
        }

        /*
         * On transmet aussi l'identifiant de l'utilisateur connecté au gabarit.
         * Si personne n'est connecté, la session renvoie null.
         * Twig pourra alors adapter l'affichage du bouton d'action.
         */
        return $this->render('details/index.html.twig', [
            'detail' => $detail,
            'avis_chauffeur' => $avisChauffeur,
            'id_utilisateur_connecte' => $sessionUtilisateur->idUtilisateur(),
        ]);
    }
}
