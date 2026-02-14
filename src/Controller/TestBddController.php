<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceCovoituragePostgresql;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestBddController extends AbstractController
{
    /*
      PLAN (TestBddController) :

      1) Objectif de ce contrôleur
         - c’est un contrôleur “de test”
         - il sert à vérifier rapidement que :
            - Symfony arrive à appeler un service
            - le service arrive à parler à PostgreSQL
           - on récupère bien des données

      2) Pourquoi c’est utile
         - quand j’ai un doute sur la connexion PDO ou la requête
         - je teste via une route simple, sans passer par du Twig

      3) Attention
         - ce contrôleur n’est pas une page “fonctionnelle”
         - en vrai projet, on ne laisse pas ça ouvert en production
         - ici c’est juste pour le TP et le debug pendant le dev
    */

    #[Route('/test-bdd', name: 'test_bdd', methods: ['GET'])]
    public function index(PersistanceCovoituragePostgresql $persistance): Response
    {
        /*
          Ici je déclenche une recherche “simple”
          - le but n’est pas d’avoir un filtre parfait
          - je veux juste voir si la requête renvoie quelque chose

          Important :
          - ce code doit être cohérent avec la signature réelle de ton service
          - si ta méthode s’appelle rechercherCovoiturages(...) et pas rechercher(...),
            il faut appeler le bon nom et passer les bons paramètres
        */

        // Exemple minimal : je récupère une liste de covoiturages
        $resultats = $persistance->rechercher(null, null, null);

        /*
          Je renvoie du JSON :
          - nb_covoiturages : combien j’en ai trouvé
          - exemple : le premier élément pour voir la structure des colonnes
        */
        return $this->json([
            'nb_covoiturages' => count($resultats),
            'exemple' => $resultats[0] ?? null,
        ]);
    }
}
