<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceEmployePostgresql;
use App\Service\SessionUtilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de l'espace employé.
 *
 * Cette classe gère les pages et les actions réservées aux employés :
 * affichage de la liste des avis à modérer, affichage des signalements,
 * consultation du détail d'un avis, consultation du détail d'un incident,
 * puis actions de validation, refus ou traitement.
 *
 * Le contrôleur garde ici le rôle lié au web :
 * il vérifie l'accès, lit la requête HTTP, contrôle les jetons CSRF,
 * prépare les messages flash et choisit la page à afficher ou la redirection.
 *
 * Les lectures et écritures en base de données sont déléguées
 * à `PersistanceEmployePostgresql`.
 *
 * @package App\Controller
 */
final class EspaceEmployeController extends AbstractController
{
    /**
     * Affiche la page principale de l'espace employé.
     *
     * Cette page contient deux onglets :
     * - les avis en attente de modération ;
     * - les incidents encore ouverts.
     *
     * Le paramètre `onglet` transmis dans l'URL permet de choisir
     * lequel doit être affiché en priorité.
     *
     * @param Request $request Requête HTTP courante.
     * @param SessionUtilisateur $sessionUtilisateur Service de session utilisateur.
     * @param PersistanceEmployePostgresql $persistanceEmploye
     *        Service qui lit les données utiles dans PostgreSQL.
     *
     * @return Response Réponse HTML rendue par Twig.
     */
    #[Route('/espace-employe', name: 'espace_employe', methods: ['GET'])]
    public function index(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceEmployePostgresql $persistanceEmploye,
    ): Response {
        /*
         * Cette page est réservée à un employé connecté.
         * Si la session ne correspond pas à ce profil,
         * l'utilisateur est renvoyé vers la connexion.
         */
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            return $this->redirectToRoute('connexion');
        }

        /*
         * On limite volontairement les valeurs possibles pour l'onglet.
         * Si la valeur reçue n'est pas reconnue, on revient à "avis".
         */
        $onglet = (string) $request->query->get('onglet', 'avis');
        $onglet = $onglet === 'signalements' ? 'signalements' : 'avis';

        /*
         * Les deux listes sont lues dans PostgreSQL par le service de persistance.
         * Le contrôleur récupère ici des tableaux déjà prêts à être affichés.
         */
        $avis = $persistanceEmploye->listerAvisEnAttente(50);
        $signalements = $persistanceEmploye->listerIncidentsOuverts(50);

        return $this->render('espace_employe/index.html.twig', [
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
            'onglet' => $onglet,
            'avis' => $avis,
            'signalements' => $signalements,
        ]);
    }

    /**
     * Récupère le détail d'un avis encore en attente.
     *
     * Cette méthode délègue la lecture à la persistance.
     * Elle sert ensuite aux pages de détail de l'espace employé.
     *
     * @param int $idAvis Identifiant de l'avis recherché.
     * @param PersistanceEmployePostgresql $persistanceEmploye
     *        Service de lecture PostgreSQL.
     *
     * @return array{
     *   id_avis:int,
     *   note:int,
     *   commentaire:string,
     *   statut_moderation:string,
     *   date_depot:string,
     *   pseudo_passager:string,
     *   email_passager:string,
     *   pseudo_chauffeur:string,
     *   email_chauffeur:string,
     *   id_covoiturage:int,
     *   date_depart:string,
     *   ville_depart:string,
     *   ville_arrivee:string
     * }|null
     */
    public function trouverAvisEnAttenteParId(
        int $idAvis,
        PersistanceEmployePostgresql $persistanceEmploye
    ): ?array {
        return $persistanceEmploye->trouverAvisEnAttenteParId($idAvis);
    }

    /**
     * Récupère le détail d'un incident encore ouvert.
     *
     * La méthode renvoie les informations du covoiturage,
     * les coordonnées du chauffeur
     * puis la liste des passagers dont la participation n'est pas annulée.
     *
     * @param int $idCovoiturage Identifiant du covoiturage concerné.
     * @param PersistanceEmployePostgresql $persistanceEmploye
     *        Service de lecture PostgreSQL.
     *
     * @return array{
     *   id_covoiturage:int,
     *   libelle:string,
     *   incident_commentaire:string,
     *   statut:string,
     *   date_depart:string,
     *   date_arrivee:string,
     *   ville_depart:string,
     *   ville_arrivee:string,
     *   pseudo_chauffeur:string,
     *   email_chauffeur:string,
     *   passagers: array<int, array{pseudo:string, email:string}>
     * }|null
     */
    public function trouverIncidentOuvertParCovoiturage(
        int $idCovoiturage,
        PersistanceEmployePostgresql $persistanceEmploye
    ): ?array {
        return $persistanceEmploye->trouverIncidentOuvertParCovoiturage($idCovoiturage);
    }

    /**
     * Valide un avis en attente de modération.
     *
     * Le contrôleur vérifie d'abord :
     * - que le compte connecté est bien un employé ;
     * - que l'identifiant d'avis est cohérent ;
     * - que le jeton CSRF est valide.
     *
     * L'écriture en base est ensuite déléguée à la persistance.
     *
     * @param Request $request Requête HTTP contenant l'identifiant et le jeton CSRF.
     * @param SessionUtilisateur $sessionUtilisateur Service de session utilisateur.
     * @param PersistanceEmployePostgresql $persistanceEmploye
     *        Service de persistance PostgreSQL.
     *
     * @return Response Redirection vers l'onglet des avis.
     */
    #[Route('/espace-employe/avis/valider', name: 'espace_employe_avis_valider', methods: ['POST'])]
    public function validerAvis(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceEmployePostgresql $persistanceEmploye,
    ): Response {
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            throw $this->createAccessDeniedException('Accès réservé employé.');
        }

        $idAvis = (int) $request->request->get('id_avis', 0);
        $token = (string) $request->request->get('_token', '');

        /*
         * Un jeton CSRF protège l'action contre une soumission frauduleuse.
         * Il permet de vérifier que le formulaire vient bien de l'application.
         */
        if ($idAvis <= 0 || !$this->isCsrfTokenValid('avis_valider_' . $idAvis, $token)) {
            $this->addFlash('erreur', 'Action refusée : jeton de sécurité invalide.');

            return $this->redirectToRoute('espace_employe', ['onglet' => 'avis']);
        }

        $idEmploye = (int) ($sessionUtilisateur->idUtilisateur() ?? 0);
        if ($idEmploye <= 0) {
            $this->addFlash('erreur', 'Impossible d’identifier le compte employé.');

            return $this->redirectToRoute('espace_employe', ['onglet' => 'avis']);
        }

        $ok = $persistanceEmploye->modererAvis($idAvis, 'VALIDE', $idEmploye);

        $this->addFlash(
            $ok ? 'succes' : 'avertissement',
            $ok ? 'Avis validé (publié).' : 'Aucun changement : avis déjà traité ou introuvable.'
        );

        return $this->redirectToRoute('espace_employe', ['onglet' => 'avis']);
    }

    /**
     * Refuse un avis en attente de modération.
     *
     * Le déroulé reste le même que pour la validation :
     * contrôle d'accès, contrôle CSRF,
     * puis délégation de l'écriture SQL à la persistance.
     *
     * @param Request $request Requête HTTP contenant l'identifiant et le jeton CSRF.
     * @param SessionUtilisateur $sessionUtilisateur Service de session utilisateur.
     * @param PersistanceEmployePostgresql $persistanceEmploye
     *        Service de persistance PostgreSQL.
     *
     * @return Response Redirection vers l'onglet des avis.
     */
    #[Route('/espace-employe/avis/refuser', name: 'espace_employe_avis_refuser', methods: ['POST'])]
    public function refuserAvis(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceEmployePostgresql $persistanceEmploye,
    ): Response {
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            throw $this->createAccessDeniedException('Accès réservé employé.');
        }

        $idAvis = (int) $request->request->get('id_avis', 0);
        $token = (string) $request->request->get('_token', '');

        if ($idAvis <= 0 || !$this->isCsrfTokenValid('avis_refuser_' . $idAvis, $token)) {
            $this->addFlash('erreur', 'Action refusée : jeton de sécurité invalide.');

            return $this->redirectToRoute('espace_employe', ['onglet' => 'avis']);
        }

        $idEmploye = (int) ($sessionUtilisateur->idUtilisateur() ?? 0);
        if ($idEmploye <= 0) {
            $this->addFlash('erreur', 'Impossible d’identifier le compte employé.');

            return $this->redirectToRoute('espace_employe', ['onglet' => 'avis']);
        }

        $ok = $persistanceEmploye->modererAvis($idAvis, 'REFUSE', $idEmploye);

        $this->addFlash(
            'avertissement',
            $ok ? 'Avis refusé (non publié).' : 'Aucun changement : avis déjà traité ou introuvable.'
        );

        return $this->redirectToRoute('espace_employe', ['onglet' => 'avis']);
    }

    /**
     * Marque un incident comme traité.
     *
     * L'action ne modifie l'incident que si le covoiturage
     * est encore en statut `INCIDENT`
     * et que le signalement n'a pas déjà été traité.
     *
     * @param Request $request Requête HTTP contenant l'identifiant et le jeton CSRF.
     * @param SessionUtilisateur $sessionUtilisateur Service de session utilisateur.
     * @param PersistanceEmployePostgresql $persistanceEmploye
     *        Service de persistance PostgreSQL.
     *
     * @return Response Redirection vers l'onglet des signalements.
     */
    #[Route('/espace-employe/incident/traite', name: 'espace_employe_incident_traite', methods: ['POST'])]
    public function marquerIncidentTraite(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceEmployePostgresql $persistanceEmploye,
    ): Response {
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            throw $this->createAccessDeniedException('Accès réservé employé.');
        }

        $idCovoiturage = (int) $request->request->get('id_covoiturage', 0);
        $token = (string) $request->request->get('_token', '');

        if ($idCovoiturage <= 0 || !$this->isCsrfTokenValid('incident_traite_' . $idCovoiturage, $token)) {
            $this->addFlash('erreur', 'Action refusée : jeton de sécurité invalide.');

            return $this->redirectToRoute('espace_employe', ['onglet' => 'signalements']);
        }

        $ok = $persistanceEmploye->marquerIncidentTraite($idCovoiturage);

        $this->addFlash(
            $ok ? 'succes' : 'avertissement',
            $ok ? 'Signalement marqué comme traité.' : 'Aucun changement : incident déjà traité ou introuvable.'
        );

        return $this->redirectToRoute('espace_employe', ['onglet' => 'signalements']);
    }

    /**
     * Affiche le détail d'un incident encore ouvert.
     *
     * La lecture détaillée est confiée à la persistance,
     * puis le contrôleur choisit soit l'affichage de la page,
     * soit le retour vers la liste avec un message.
     *
     * @param int $idCovoiturage Identifiant du covoiturage concerné.
     * @param SessionUtilisateur $sessionUtilisateur Service de session utilisateur.
     * @param PersistanceEmployePostgresql $persistanceEmploye
     *        Service de lecture PostgreSQL.
     *
     * @return Response Réponse HTML rendue par Twig ou redirection.
     */
    #[Route('/espace-employe/incident/{idCovoiturage}', name: 'espace_employe_incident_detail', methods: ['GET'])]
    public function incidentDetail(
        int $idCovoiturage,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceEmployePostgresql $persistanceEmploye,
    ): Response {
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            return $this->redirectToRoute('connexion');
        }

        $incident = $this->trouverIncidentOuvertParCovoiturage($idCovoiturage, $persistanceEmploye);

        if ($incident === null) {
            $this->addFlash('avertissement', 'Incident introuvable ou déjà résolu.');

            return $this->redirectToRoute('espace_employe', ['onglet' => 'signalements']);
        }

        return $this->render('espace_employe/incident_detail.html.twig', [
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
            'incident' => $incident,
        ]);
    }

    /**
     * Affiche le détail d'un avis encore en attente.
     *
     * Le contrôleur délègue la lecture SQL,
     * puis choisit soit l'affichage de la page,
     * soit le retour vers la liste des avis.
     *
     * @param int $idAvis Identifiant de l'avis.
     * @param SessionUtilisateur $sessionUtilisateur Service de session utilisateur.
     * @param PersistanceEmployePostgresql $persistanceEmploye
     *        Service de lecture PostgreSQL.
     *
     * @return Response Réponse HTML rendue par Twig ou redirection.
     */
    #[Route('/espace-employe/avis/{idAvis}', name: 'espace_employe_avis_detail', methods: ['GET'])]
    public function avisDetail(
        int $idAvis,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceEmployePostgresql $persistanceEmploye,
    ): Response {
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            return $this->redirectToRoute('connexion');
        }

        $avis = $this->trouverAvisEnAttenteParId($idAvis, $persistanceEmploye);

        if ($avis === null) {
            $this->addFlash('avertissement', 'Avis introuvable ou déjà modéré.');

            return $this->redirectToRoute('espace_employe', ['onglet' => 'avis']);
        }

        return $this->render('espace_employe/avis_detail.html.twig', [
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
            'avis' => $avis,
        ]);
    }
}