<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConnexionPostgresql;
use App\Service\PersistanceEmployePostgresql;
use App\Service\SessionUtilisateur;
use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EspaceEmployeController extends AbstractController
{
    /*
      PLAN (EspaceEmployeController) :

      1) Branchements / dépendances
         - garder le contrôleur "simple" : il orchestre, il ne porte pas la logique métier
         - récupérer une connexion PDO via ConnexionPostgresql (pour les requêtes "spécifiques")
         - utiliser PersistanceEmployePostgresql pour les opérations métier (modération / incidents)
         - utiliser SessionUtilisateur comme source unique de vérité (connecté + rôle employé)

      2) Affichage de l’espace employé (GET /espace-employe)
         - page protégée : employé connecté obligatoire
         - choisir un onglet (avis / signalements) via ?onglet=
         - récupérer les 2 listes (avis en attente / incidents ouverts)
         - rendre Twig avec les données

      3) Outils internes (lecture détaillée)
         - trouver un avis EN_ATTENTE par id (pour afficher un détail)
         - trouver un incident INCIDENT non résolu par covoiturage (données + liste passagers)
         - garde-fous : id > 0, sinon null
         - conversion "propre" des types pour Twig (int/string)

      4) Actions employé (POST)
         - CSRF obligatoire
         - avis : valider / refuser
         - incident : marquer traité
         - toujours rebasculer sur le bon onglet + flash utilisateur
    */

    /**
     * Connexion PDO (accès bas niveau) utilisée par les méthodes "trouver..."
     * Astuce débutante : on la prépare une fois dans le constructeur plutôt
     * que de la recréer à chaque appel.
     */
    private PDO $pdo;

    public function __construct(ConnexionPostgresql $connexionPostgresql)
    {
        // Ici on récupère directement l'objet PDO.
        // IMPORTANT : on n'ajoute aucune fonctionnalité, on branche juste correctement.
        $this->pdo = $connexionPostgresql->obtenirPdo();
    }

    #[Route('/espace-employe', name: 'espace_employe', methods: ['GET'])]
    public function index(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceEmployePostgresql $persistanceEmploye,
    ): Response {
        // 1) Sécurité : l’espace employé doit être inaccessible hors employé connecté.
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            return $this->redirectToRoute('connexion');
        }

        // 2) Onglets : on ne garde que 2 valeurs possibles (évite les surprises).
        $onglet = (string) $request->query->get('onglet', 'avis');
        $onglet = $onglet === 'signalements' ? 'signalements' : 'avis';

        // 3) Données d'écran : on charge les listes (limite volontaire pour garder la page rapide).
        $avis = $persistanceEmploye->listerAvisEnAttente(50);
        $signalements = $persistanceEmploye->listerIncidentsOuverts(50);

        // 4) Affichage Twig.
        return $this->render('espace_employe/index.html.twig', [
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
            'onglet' => $onglet,
            'avis' => $avis,
            'signalements' => $signalements,
        ]);
    }

    /**
     * Trouver 1 avis EN_ATTENTE par identifiant.
     * Retour : tableau "prêt pour Twig" (types normalisés) ou null si introuvable.
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
    public function trouverAvisEnAttenteParId(int $idAvis): ?array
    {
        // Garde-fou : pas de requête si l'id est invalide.
        if ($idAvis <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
              a.id_avis,
              a.note,
              COALESCE(a.commentaire, '') AS commentaire,
              a.statut_moderation,
              to_char(a.date_depot, 'YYYY-MM-DD HH24:MI') AS date_depot,
              u_pa.pseudo AS pseudo_passager,
              u_pa.email AS email_passager,
              u_ch.pseudo AS pseudo_chauffeur,
              u_ch.email AS email_chauffeur,
              c.id_covoiturage,
              to_char(c.date_heure_depart, 'YYYY-MM-DD HH24:MI') AS date_depart,
              c.ville_depart,
              c.ville_arrivee
            FROM avis a
            JOIN participation p ON p.id_participation = a.id_participation
            JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
            JOIN utilisateur u_pa ON u_pa.id_utilisateur = p.id_utilisateur
            JOIN utilisateur u_ch ON u_ch.id_utilisateur = c.id_utilisateur
            WHERE a.id_avis = :id_avis
              AND a.statut_moderation = 'EN_ATTENTE'
            LIMIT 1
        ");
        $stmt->execute(['id_avis' => $idAvis]);

        /** @var array<string, mixed>|false $ligne */
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ligne)) {
            return null;
        }

        // On renvoie un tableau propre (int/string) : Twig adore ça.
        return [
            'id_avis' => (int) ($ligne['id_avis'] ?? 0),
            'note' => (int) ($ligne['note'] ?? 0),
            'commentaire' => (string) ($ligne['commentaire'] ?? ''),
            'statut_moderation' => (string) ($ligne['statut_moderation'] ?? ''),
            'date_depot' => (string) ($ligne['date_depot'] ?? ''),
            'pseudo_passager' => (string) ($ligne['pseudo_passager'] ?? ''),
            'email_passager' => (string) ($ligne['email_passager'] ?? ''),
            'pseudo_chauffeur' => (string) ($ligne['pseudo_chauffeur'] ?? ''),
            'email_chauffeur' => (string) ($ligne['email_chauffeur'] ?? ''),
            'id_covoiturage' => (int) ($ligne['id_covoiturage'] ?? 0),
            'date_depart' => (string) ($ligne['date_depart'] ?? ''),
            'ville_depart' => (string) ($ligne['ville_depart'] ?? ''),
            'ville_arrivee' => (string) ($ligne['ville_arrivee'] ?? ''),
        ];
    }

    /**
     * Trouver 1 incident ouvert (statut INCIDENT + non résolu) pour un covoiturage.
     * Retour : infos covoiturage + chauffeur + liste passagers non annulés, ou null.
     *
     * @return array{
     *   id_covoiturage:int,
     *   libelle:string,
     *   incident_commentaire:string,
     *   statut:string,
     *   date_depart:string,
     *   ville_depart:string,
     *   ville_arrivee:string,
     *   pseudo_chauffeur:string,
     *   email_chauffeur:string,
     *   passagers: array<int, array{pseudo:string, email:string}>
     * }|null
     */
    public function trouverIncidentOuvertParCovoiturage(int $idCovoiturage): ?array
    {
        if ($idCovoiturage <= 0) {
            return null;
        }

        // 1) Données de base de l’incident (1 ligne max)
        $stmt = $this->pdo->prepare("
            SELECT
              c.id_covoiturage,
              COALESCE(c.incident_commentaire, '') AS incident_commentaire,
              COALESCE(c.incident_commentaire, 'Incident') AS libelle,
              c.statut_covoiturage AS statut,
              to_char(c.date_heure_depart, 'YYYY-MM-DD HH24:MI') AS date_depart,
              c.ville_depart,
              c.ville_arrivee,
              u_ch.pseudo AS pseudo_chauffeur,
              u_ch.email AS email_chauffeur
            FROM covoiturage c
            JOIN utilisateur u_ch ON u_ch.id_utilisateur = c.id_utilisateur
            WHERE c.id_covoiturage = :id
              AND c.statut_covoiturage = 'INCIDENT'
              AND c.incident_resolu = false
            LIMIT 1
        ");
        $stmt->execute(['id' => $idCovoiturage]);

        /** @var array<string, mixed>|false $ligne */
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ligne)) {
            return null;
        }

        // 2) Passagers du covoiturage (hors annulations)
        $stmt2 = $this->pdo->prepare("
            SELECT
              u_pa.pseudo,
              u_pa.email
            FROM participation p
            JOIN utilisateur u_pa ON u_pa.id_utilisateur = p.id_utilisateur
            WHERE p.id_covoiturage = :id
              AND p.est_annulee = false
            ORDER BY p.date_heure_confirmation DESC
        ");
        $stmt2->execute(['id' => $idCovoiturage]);

        /** @var array<int, array<string, mixed>> $lignesPassagers */
        $lignesPassagers = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $passagers = [];
        foreach ($lignesPassagers as $lp) {
            $passagers[] = [
                'pseudo' => (string) ($lp['pseudo'] ?? ''),
                'email' => (string) ($lp['email'] ?? ''),
            ];
        }

        return [
            'id_covoiturage' => (int) ($ligne['id_covoiturage'] ?? 0),
            'libelle' => (string) ($ligne['libelle'] ?? ''),
            'incident_commentaire' => (string) ($ligne['incident_commentaire'] ?? ''),
            'statut' => (string) ($ligne['statut'] ?? ''),
            'date_depart' => (string) ($ligne['date_depart'] ?? ''),
            'ville_depart' => (string) ($ligne['ville_depart'] ?? ''),
            'ville_arrivee' => (string) ($ligne['ville_arrivee'] ?? ''),
            'pseudo_chauffeur' => (string) ($ligne['pseudo_chauffeur'] ?? ''),
            'email_chauffeur' => (string) ($ligne['email_chauffeur'] ?? ''),
            'passagers' => $passagers,
        ];
    }

    #[Route('/espace-employe/avis/valider', name: 'espace_employe_avis_valider', methods: ['POST'])]
    public function validerAvis(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceEmployePostgresql $persistanceEmploye,
    ): Response {
        // Sécurité "stricte" : une action POST employé ne doit jamais rediriger silencieusement.
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            throw $this->createAccessDeniedException('Accès réservé employé.');
        }

        $idAvis = (int) $request->request->get('id_avis', 0);
        $token = (string) $request->request->get('_token', '');

        // CSRF + id : garde-fous minimum avant de toucher à la base.
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

        // Petite incohérence d’origine : les 2 branches utilisaient 'avertissement'.
        // Je garde la fonctionnalité, mais je mets un niveau de message plus logique.
        $this->addFlash(
            'avertissement',
            $ok ? 'Avis refusé (non publié).' : 'Aucun changement : avis déjà traité ou introuvable.'
        );

        return $this->redirectToRoute('espace_employe', ['onglet' => 'avis']);
    }

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
    #[Route('/espace-employe/incident/{idCovoiturage}', name: 'espace_employe_incident_detail', methods: ['GET'])]
    public function incidentDetail(
        int $idCovoiturage,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        // 1) Sécurité : employé uniquement
        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            return $this->redirectToRoute('connexion');
        }

        // 2) Lecture des données (on réutilise ta méthode existante)
        $incident = $this->trouverIncidentOuvertParCovoiturage($idCovoiturage);

        if ($incident === null) {
            $this->addFlash('avertissement', 'Incident introuvable ou déjà résolu.');
            return $this->redirectToRoute('espace_employe', ['onglet' => 'signalements']);
        }

        // 3) Affichage
        return $this->render('espace_employe/incident_detail.html.twig', [
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
            'incident' => $incident,
        ]);
    }
    #[Route('/espace-employe/avis/{idAvis}', name: 'espace_employe_avis_detail', methods: ['GET'])]
    public function avisDetail(
        int $idAvis,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        /*
          PLAN (avisDetail) :

          1) Sécurité : employé connecté obligatoire
          2) Charger l’avis EN_ATTENTE via trouverAvisEnAttenteParId()
          3) Si introuvable : message + retour liste
          4) Sinon : afficher la page avis_detail.html.twig
        */

        if (!$sessionUtilisateur->estConnecte() || !$sessionUtilisateur->estEmploye()) {
            return $this->redirectToRoute('connexion');
        }

        $avis = $this->trouverAvisEnAttenteParId($idAvis);
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
