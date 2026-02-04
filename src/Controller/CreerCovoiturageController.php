<?php
declare(strict_types=1);

namespace App\Controller;

use App\Application\CreerCovoiturage;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreerCovoiturageController extends AbstractController
{
    #[Route('/api/covoiturages', name: 'api_creer_covoiturage', methods: ['POST'])]
    public function creer(Request $requete, CreerCovoiturage $service): JsonResponse
    {
        $donnees = json_decode($requete->getContent(), true);

        if (!is_array($donnees)) {
            return $this->json(['erreur' => 'JSON invalide.'], 400);
        }

        $champs_obligatoires = [
            'id_utilisateur',
            'id_voiture',
            'date_heure_depart',
            'date_heure_arrivee',
            'adresse_depart',
            'adresse_arrivee',
            'ville_depart',
            'ville_arrivee',
            'nb_places_dispo',
            'prix_credits'
        ];

        foreach ($champs_obligatoires as $champ) {
            if (!array_key_exists($champ, $donnees)) {
                return $this->json(['erreur' => "Champ manquant : {$champ}."], 400);
            }
        }

        try {
            $id_covoiturage = $service->executer(
                (int) $donnees['id_utilisateur'],
                (int) $donnees['id_voiture'],
                new DateTimeImmutable((string) $donnees['date_heure_depart']),
                new DateTimeImmutable((string) $donnees['date_heure_arrivee']),
                (string) $donnees['adresse_depart'],
                (string) $donnees['adresse_arrivee'],
                (string) $donnees['ville_depart'],
                (string) $donnees['ville_arrivee'],
                (int) $donnees['nb_places_dispo'],
                (int) $donnees['prix_credits']
            );
        } catch (\Throwable $e) {
            return $this->json(['erreur' => $e->getMessage()], 400);
        }

        return $this->json(
            ['message' => 'Covoiturage créé.', 'id_covoiturage' => $id_covoiturage],
            201
        );
    }
}
