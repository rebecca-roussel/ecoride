<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

final class PersistanceCovoituragePostgresql
{
    private PDO $pdo;

    public function __construct(ConnexionPostgresql $connexion)
    {
        // Dépendance simple : le service "ConnexionPostgresql" sait fabriquer le PDO.
        $this->pdo = $connexion->obtenirPdo();
    }

    /**
     * Recherche des covoiturages selon départ / arrivée / date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rechercher(?string $depart, ?string $arrivee, ?string $dateDepart): array
    {
        $sql = '
            SELECT
                c.id_covoiturage,
                c.ville_depart,
                c.ville_arrivee,
                c.date_heure_depart,
                c.date_heure_arrivee,
                c.nb_places_dispo,
                c.prix_credits,
                c.statut_covoiturage,
                u.pseudo AS pseudo_chauffeur,
                u.photo_path AS photo_chauffeur,
                v.energie
            FROM covoiturage c
            JOIN utilisateur u ON u.id_utilisateur = c.id_utilisateur
            LEFT JOIN voiture v ON v.id_voiture = c.id_voiture
            WHERE c.nb_places_dispo >= 1
              AND c.statut_covoiturage = :statut
        ';

        $parametres = [
            'statut' => 'PLANIFIE',
        ];

        if ($depart !== null && trim($depart) !== '') {
            $sql .= ' AND c.ville_depart ILIKE :ville_depart';
            $parametres['ville_depart'] = '%' . trim($depart) . '%';
        }

        if ($arrivee !== null && trim($arrivee) !== '') {
            $sql .= ' AND c.ville_arrivee ILIKE :ville_arrivee';
            $parametres['ville_arrivee'] = '%' . trim($arrivee) . '%';
        }

        if ($dateDepart !== null && trim($dateDepart) !== '') {
            // On compare uniquement la date, sans l’heure (utile pour un premier jet).
            $sql .= ' AND c.date_heure_depart::date = :date_depart';
            $parametres['date_depart'] = trim($dateDepart);
        }

        $sql .= ' ORDER BY c.date_heure_depart ASC';

        $requete = $this->pdo->prepare($sql);
        $requete->execute($parametres);

        return $requete->fetchAll();
    }
}
