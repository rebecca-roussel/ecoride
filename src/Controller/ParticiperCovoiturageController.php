<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConnexionPostgresql;
use App\Service\SessionUtilisateur;
use PDO;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ParticiperCovoiturageController extends AbstractController
{
    /*
      PLAN (ParticiperCovoiturageController) :

      1) Sécurité
         - utilisateur connecté obligatoire
         - requête POST + jeton CSRF

      2) Règles métier minimales
         - covoiturage PLANIFIE
         - nb_places_dispo > 0
         - le chauffeur ne peut pas participer à son propre trajet
         - pas de double participation (unicité utilisateur+covoiturage)
         - crédits passager suffisants

      3) Transaction SQL (tout ou rien)
         - verrouiller la ligne covoiturage (FOR UPDATE)
         - verrouiller la ligne utilisateur (FOR UPDATE)
         - insérer participation
         - laisser la BDD gérer places + débit (via déclencheur)
    */

    #[Route('/participer/{id}', name: 'participer_covoiturage', methods: ['POST'])]
    public function participer(
        int $id,
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        ConnexionPostgresql $connexion,
    ): Response {
        // 0) Garde-fou simple : id cohérent (évite les routes bidons)
        if ($id <= 0) {
            $this->addFlash('erreur', 'Covoiturage invalide.');
            return $this->redirectToRoute('resultats');
        }

        // 1) Sécurité : connecté
        $utilisateur = $sessionUtilisateur->obtenirUtilisateurConnecte();
        if ($utilisateur === null) {
            $this->addFlash('erreur', 'Veuillez vous connecter pour participer.');
            return $this->redirectToRoute('connexion');
        }

        $idPassager = (int) ($utilisateur['id_utilisateur'] ?? 0);
        if ($idPassager <= 0) {
            // Cas très rare : session incohérente (on évite d’aller plus loin)
            $this->addFlash('erreur', 'Session invalide. Veuillez vous reconnecter.');
            return $this->redirectToRoute('connexion');
        }

        // 2) CSRF (même nom de token que dans Twig)
        $jeton = (string) $requete->request->get('_token', '');
        if (!$this->isCsrfTokenValid('participer_covoiturage_' . $id, $jeton)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $pdo = $connexion->obtenirPdo();

        try {
            $pdo->beginTransaction();

            // A) Verrouiller le covoiturage (évite les doubles participations simultanées)
            $stmt = $pdo->prepare("
                SELECT
                    id_covoiturage,
                    id_utilisateur AS id_chauffeur,
                    nb_places_dispo,
                    prix_credits,
                    statut_covoiturage
                FROM covoiturage
                WHERE id_covoiturage = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $id]);
            $covoit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$covoit) {
                $pdo->rollBack();
                $this->addFlash('erreur', 'Covoiturage introuvable.');
                return $this->redirectToRoute('resultats');
            }

            // B) Covoiturage réservable : uniquement PLANIFIE
            if (($covoit['statut_covoiturage'] ?? '') !== 'PLANIFIE') {
                $pdo->rollBack();
                $this->addFlash('erreur', 'Ce covoiturage n’est pas réservable.');
                return $this->redirectToRoute('details', ['id' => $id]);
            }

            // C) Chauffeur ≠ passager
            $idChauffeur = (int) ($covoit['id_chauffeur'] ?? 0);
            if ($idChauffeur === $idPassager) {
                $pdo->rollBack();
                $this->addFlash('erreur', 'Vous ne pouvez pas participer à votre propre covoiturage.');
                return $this->redirectToRoute('details', ['id' => $id]);
            }

            // D) Places disponibles (vérif “humainement logique” avant d’insérer)
            $places = (int) ($covoit['nb_places_dispo'] ?? 0);
            if ($places <= 0) {
                $pdo->rollBack();
                $this->addFlash('erreur', 'Ce covoiturage est complet.');
                return $this->redirectToRoute('details', ['id' => $id]);
            }

            // E) Prix cohérent
            $prix = (int) ($covoit['prix_credits'] ?? 0);
            if ($prix <= 0) {
                // Normalement impossible grâce à la contrainte BDD, mais garde-fou
                $pdo->rollBack();
                $this->addFlash('erreur', 'Prix invalide.');
                return $this->redirectToRoute('details', ['id' => $id]);
            }

            // F) Vérifier crédits passager (on verrouille aussi la ligne utilisateur)
            $stmt = $pdo->prepare("
                SELECT credits
                FROM utilisateur
                WHERE id_utilisateur = :id_passager
                FOR UPDATE
            ");
            $stmt->execute(['id_passager' => $idPassager]);
            $creditsPassager = (int) $stmt->fetchColumn();

            if ($creditsPassager < $prix) {
                $pdo->rollBack();
                $this->addFlash('erreur', 'Crédits insuffisants pour participer à ce covoiturage.');
                return $this->redirectToRoute('details', ['id' => $id]);
            }

            // G) Insérer la participation
            //    - l’unicité (id_utilisateur, id_covoiturage) protège aussi contre les doublons
            $stmt = $pdo->prepare("
                INSERT INTO participation (
                    date_heure_confirmation,
                    credits_utilises,
                    est_annulee,
                    id_utilisateur,
                    id_covoiturage
                )
                VALUES (NOW(), :credits_utilises, false, :id_utilisateur, :id_covoiturage)
            ");
            $stmt->execute([
                'credits_utilises' => $prix,
                'id_utilisateur' => $idPassager,
                'id_covoiturage' => $id,
            ]);

            /*
              IMPORTANT (sinon on se tire une balle dans le pied) :

              - Ta base a déjà un déclencheur :
                declencheur_participation_places_debit (AFTER INSERT/DELETE/UPDATE sur participation)

              - Donc la BDD s’occupe toute seule :
                * de décrémenter / ré-incrémenter nb_places_dispo
                * de débiter / rembourser les crédits

              => Si on refait ces UPDATE ici, on aurait un double débit
                 et des places qui diminuent 2 fois.
            */

            $pdo->commit();

            $this->addFlash('succes', 'Participation confirmée.');
            return $this->redirectToRoute('details', ['id' => $id]);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Cas de doublon (unicité) : Postgres renvoie souvent SQLSTATE 23505
            if ($e->getCode() === '23505') {
                $this->addFlash('erreur', 'Vous participez déjà à ce covoiturage.');
                return $this->redirectToRoute('details', ['id' => $id]);
            }

            $this->addFlash('erreur', 'Erreur lors de la participation.');
            return $this->redirectToRoute('details', ['id' => $id]);
        }
    }
}
