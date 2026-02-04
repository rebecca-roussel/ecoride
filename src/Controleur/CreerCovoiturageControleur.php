<?php
declare(strict_types=1);

namespace App\Controleur;

use App\Application\CreerCovoiturage;
use DateTimeImmutable;
use InvalidArgumentException;
use PDOException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/*
 * Je garde ce fichier simple :
 * - Ici je m'occupe uniquement de l'entrée "web" (requête HTTP).
 * - Je lis le JSON, je le transforme en données utilisables.
 * - Ensuite j'appelle CreerCovoiturage, qui fait le travail sérieux (règles + SQL).
 *
 * Exemple de JSON à envoyer :
 * {
 *   "id_utilisateur": 3,
 *   "id_voiture": 12,
 *   "date_heure_depart": "2026-02-05T10:30:00",
 *   "date_heure_arrivee": "2026-02-05T11:15:00",
 *   "adresse_depart": "12 rue des Fleurs",
 *   "adresse_arrivee": "1 avenue du Lac",
 *   "ville_depart": "Annemasse",
 *   "ville_arrivee": "Genève",
 *   "nb_places_dispo": 3,
 *   "prix_credits": 8
 * }
 */
final class CreerCovoiturageControleur
{
    public function __construct(private CreerCovoiturage $service)
    {
    }

    #[Route('/api/covoiturages', name: 'api_creer_covoiturage', methods: ['POST'])]
    public function __invoke(Request $requete): JsonResponse
    {
        // Je récupère le texte brut envoyé par le client
        $contenu = $requete->getContent();

        if ($contenu === '') {
            return new JsonResponse(['erreur' => 'Je n’ai rien reçu (JSON attendu).'], 400);
        }

        // Je transforme le JSON en tableau PHP
        $donnees = json_decode($contenu, true);

        if (!is_array($donnees)) {
            return new JsonResponse(['erreur' => 'Le JSON est invalide (impossible à lire).'], 400);
        }

        // Je vérifie vite fait que les champs existent (sinon ça plantera plus loin)
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
            'prix_credits',
        ];

        foreach ($champs_obligatoires as $champ) {
            if (!array_key_exists($champ, $donnees)) {
                return new JsonResponse(['erreur' => "Il manque : {$champ}."], 400);
            }
        }

        // Je force les types (ça évite des surprises : "12" devient 12)
        $id_utilisateur = (int) $donnees['id_utilisateur'];
        $id_voiture = (int) $donnees['id_voiture'];
        $nb_places_dispo = (int) $donnees['nb_places_dispo'];
        $prix_credits = (int) $donnees['prix_credits'];

        // Je nettoie les textes (je retire les espaces inutiles)
        $adresse_depart = trim((string) $donnees['adresse_depart']);
        $adresse_arrivee = trim((string) $donnees['adresse_arrivee']);
        $ville_depart = trim((string) $donnees['ville_depart']);
        $ville_arrivee = trim((string) $donnees['ville_arrivee']);

        // Je transforme les dates en vrais objets DateTime (sinon je ne peux pas comparer correctement)
        try {
            $date_heure_depart = new DateTimeImmutable((string) $donnees['date_heure_depart']);
            $date_heure_arrivee = new DateTimeImmutable((string) $donnees['date_heure_arrivee']);
        } catch (\Throwable $e) {
            return new JsonResponse(['erreur' => 'Dates illisibles. Exemple attendu : 2026-02-05T10:30:00'], 400);
        }

        // Ici, je passe la main au service (lui gère la logique + la base PostgreSQL)
        try {
            $id_covoiturage = $this->service->executer(
                $id_utilisateur,
                $id_voiture,
                $date_heure_depart,
                $date_heure_arrivee,
                $adresse_depart,
                $adresse_arrivee,
                $ville_depart,
                $ville_arrivee,
                $nb_places_dispo,
                $prix_credits
            );

            // 201 = création OK
            return new JsonResponse(
                ['id_covoiturage' => $id_covoiturage],
                201
            );
        } catch (InvalidArgumentException $e) {
            // Erreur "utilisateur" : données incohérentes (ex : voiture pas à lui)
            return new JsonResponse(['erreur' => $e->getMessage()], 400);
        } catch (PDOException $e) {
            // Erreur côté base (je reste vague volontairement)
            return new JsonResponse(['erreur' => 'Erreur serveur (base de données).'], 500);
        } catch (\Throwable $e) {
            // Tout le reste : je renvoie une erreur serveur générique
            return new JsonResponse(['erreur' => 'Erreur serveur.'], 500);
        }
    }
}

