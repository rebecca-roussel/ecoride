<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceHistoriquePostgresql;
use App\Service\SessionUtilisateur;
use RuntimeException;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;


final class HistoriqueController extends AbstractController
{
    /*
      PLAN (HistoriqueController) :

      1) Afficher l’historique
         - page protégée : utilisateur connecté obligatoire
         - 2 onglets :
           a) mes covoiturages publiés
           b) mes participations

      2) Pages dérivées (GET)
         a) Formulaire incident (chauffeur) : covoiturage EN_COURS ou TERMINE
         b) Formulaire satisfaction (passager) : covoiturage TERMINE + validation EN_ATTENTE

      3) Actions (POST)
         - CSRF obligatoire
         - fail fast : session -> csrf -> id > 0 -> (autres paramètres)
         - la persistance applique les règles métier (propriétaire, statut, déjà annulé…)

         a) Annuler participation
         b) Annuler covoiturage
         c) Démarrer covoiturage
         d) Terminer covoiturage
         e) Déclarer incident (commentaire obligatoire)
         f) Enregistrer satisfaction (note + commentaire)

      4) Journal MongoDB
         - tracer ouverture de page
         - tracer succès
         - tracer refus (csrf, id invalide, règle métier)
         - tracer erreurs techniques
    */

    /* AFFICHAGE PRINCIPAL*/

    #[Route('/historique', name: 'historique', methods: ['GET'])]
    public function index(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): Response {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        // Onglet actif, uniquement 2 valeurs acceptées
        $onglet = (string) $requete->query->get('onglet', 'covoiturages');
        if (!in_array($onglet, ['covoiturages', 'participations'], true)) {
            $onglet = 'covoiturages';
        }

        // Charger uniquement l'onglet demandé 
        $covoituragesPublies = [];
        $participations = [];

        if ($onglet === 'covoiturages') {
            $covoituragesPublies = $persistance->listerCovoituragesPublies($idUtilisateur);
        } else {
            $participations = $persistance->listerParticipations($idUtilisateur);
        }

        // Journal : ouverture de page
        $journalEvenements->enregistrer('page_ouverte', 'utilisateur', $idUtilisateur, [
            'page' => 'historique',
            'onglet' => $onglet,
        ]);

        return $this->render('historique/index.html.twig', [
            'onglet' => $onglet,
            'covoiturages_publies' => $covoituragesPublies,
            'participations' => $participations,
        ]);
    }

    /* PAGES DÉRIVÉES */

    #[Route('/historique/incident/{id}', name: 'incident_formulaire', methods: ['GET'])]
    public function afficherFormulaireIncident(
        int $id,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): Response {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        // Si route invalide
        if ($id <= 0) {
            $this->addFlash('erreur', 'Trajet invalide.');
            $this->logRefus($journalEvenements, 'incident_formulaire_refuse', 'id_invalide', $idUtilisateur, 'covoiturage', $id);
            return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
        }

        // Sécurité : uniquement le chauffeur autorisé + statut cohérent 
        if (!$persistance->peutDeclarerIncident($idUtilisateur, $id)) {
            $this->addFlash('erreur', "Accès refusé ou trajet non éligible à un incident.");
            $this->logRefus($journalEvenements, 'incident_formulaire_refuse', 'acces_interdit', $idUtilisateur, 'covoiturage', $id);
            return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
        }

        $journalEvenements->enregistrer('page_ouverte', 'utilisateur', $idUtilisateur, [
            'page' => 'incident_formulaire',
            'id_covoiturage' => $id,
        ]);

        return $this->render('historique/incident.html.twig', [
            'id_covoiturage' => $id,
        ]);
    }

    #[Route('/historique/satisfaction/{id}', name: 'satisfaction_formulaire', methods: ['GET'])]
    public function afficherFormulaireSatisfaction(
        int $id,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): Response {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        if ($id <= 0) {
            $this->addFlash('erreur', 'Trajet invalide.');
            $this->logRefus($journalEvenements, 'satisfaction_formulaire_refuse', 'id_invalide', $idUtilisateur, 'covoiturage', $id);
            return $this->redirectToRoute('historique', ['onglet' => 'participations']);
        }

        // Sécurité : uniquement le passager autorisé + statut cohérent + avis pas encore soumis
        if (!$persistance->peutDonnerAvis($idUtilisateur, $id)) {
            $this->addFlash('erreur', "Accès refusé ou avis déjà donné.");
            $this->logRefus($journalEvenements, 'satisfaction_formulaire_refuse', 'acces_interdit', $idUtilisateur, 'covoiturage', $id);
            return $this->redirectToRoute('historique', ['onglet' => 'participations']);
        }

        $journalEvenements->enregistrer('page_ouverte', 'utilisateur', $idUtilisateur, [
            'page' => 'satisfaction_formulaire',
            'id_covoiturage' => $id,
        ]);

        return $this->render('historique/satisfaction.html.twig', [
            'id_covoiturage' => $id,
            'ancien' => [],
            'erreurs' => [],
        ]);
    }

    /* ACTIONS PASSAGER */

    #[Route('/historique/annuler-participation', name: 'annuler_participation', methods: ['POST'])]
    public function annulerParticipation(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): RedirectResponse {
        $resultat = $this->executerActionSimple(
            $requete,
            $sessionUtilisateur,
            $persistance,
            $journalEvenements,
            'annuler_participation',
            'participation',
            'id_participation',
            'annulerParticipation',
            'Participation annulée.',
            'participations'
        );

        return $resultat['reponse'];
    }

    #[Route('/historique/enregistrer-satisfaction', name: 'enregistrer_satisfaction', methods: ['POST'])]
    public function enregistrerSatisfaction(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): Response {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        // Sécurité : CSRF
        if (!$this->isCsrfTokenValid('enregistrer_satisfaction', (string) $requete->request->get('_token'))) {
            $this->logRefus($journalEvenements, 'satisfaction_refusee', 'csrf_invalide', $idUtilisateur, 'covoiturage', 0);
            $this->addFlash('erreur', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('historique', ['onglet' => 'participations']);
        }

        $idCovoiturage = (int) $requete->request->get('id_covoiturage', 0);
        $note = (int) $requete->request->get('note', 0);
        $commentaire = trim((string) $requete->request->get('commentaire', ''));

        // Validation côté contrôleur
        $erreurs = [];
        if ($idCovoiturage <= 0) {
            $erreurs['id_covoiturage'] = 'Trajet invalide.';
        }
        if ($note < 1 || $note > 5) {
            $erreurs['note'] = 'La note doit être comprise entre 1 et 5.';
        }
        if ($commentaire !== '' && mb_strlen($commentaire) > 1000) {
            $erreurs['commentaire'] = 'Commentaire trop long (1000 caractères maximum).';
        }

        // Si erreur réaffiche le formulaire avec les valeurs saisies
        if (!empty($erreurs)) {
            $this->logRefus($journalEvenements, 'satisfaction_refusee', 'parametres_invalides', $idUtilisateur, 'covoiturage', $idCovoiturage);
            return $this->render('historique/satisfaction.html.twig', [
                'id_covoiturage' => $idCovoiturage,
                'ancien' => [
                    'note' => $note > 0 ? $note : '',
                    'commentaire' => $commentaire,
                ],
                'erreurs' => $erreurs,
            ]);
        }

        // Sécurité: revalide que l'utilisateur peut bien donner son avis au moment opportun
        if (!$persistance->peutDonnerAvis($idUtilisateur, $idCovoiturage)) {
            $this->addFlash('erreur', "Accès refusé ou avis déjà donné.");
            $this->logRefus($journalEvenements, 'satisfaction_refusee', 'acces_interdit', $idUtilisateur, 'covoiturage', $idCovoiturage);
            return $this->redirectToRoute('historique', ['onglet' => 'participations']);
        }

        try {
            // La persistance fera les vérifications 
            $persistance->enregistrerSatisfaction($idUtilisateur, $idCovoiturage, $note, $commentaire);

            $this->logSucces($journalEvenements, 'satisfaction_enregistree', $idUtilisateur, 'covoiturage', $idCovoiturage);
            $this->addFlash('succes', 'Merci pour votre avis !');
        } catch (RuntimeException $e) {
            $this->logRefus($journalEvenements, 'satisfaction_refusee', 'regle_metier', $idUtilisateur, 'covoiturage', $idCovoiturage, $e->getMessage());
            $this->addFlash('erreur', $e->getMessage());


            return $this->render('historique/satisfaction.html.twig', [
                'id_covoiturage' => $idCovoiturage,
                'ancien' => [
                    'note' => $note,
                    'commentaire' => $commentaire,
                ],
                'erreurs' => [],
            ]);
        } catch (Throwable $e) {
            $this->logErreur($journalEvenements, 'satisfaction_erreur', $idUtilisateur, $e, 'enregistrer_satisfaction', 'covoiturage', $idCovoiturage);
            $this->addFlash('erreur', 'Erreur technique : enregistrement impossible.');
        }

        return $this->redirectToRoute('historique', ['onglet' => 'participations']);
    }

    #[Route('/historique/annuler-covoiturage', name: 'annuler_covoiturage', methods: ['POST'])]
    public function annulerCovoiturage(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements,
        MailerInterface $serviceCourriel
    ): RedirectResponse {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];
        $idCovoiturage = (int) $requete->request->get('id_covoiturage', 0);

        // 1) Préparer la liste des emails 
        $emailsPassagers = [];
        if ($idCovoiturage > 0) {
            try {
                $emailsPassagers = $persistance->listerEmailsParticipants($idUtilisateur, $idCovoiturage);
            } catch (Throwable $e) {
                $this->logErreur(
                    $journalEvenements,
                    'courriel_annulation_preparation_erreur',
                    $idUtilisateur,
                    $e,
                    'annuler_covoiturage',
                    'covoiturage',
                    $idCovoiturage
                );
                $emailsPassagers = [];
            }
        }

        // 2) Annuler via le helper 
        $resultat = $this->executerActionSimple(
            $requete,
            $sessionUtilisateur,
            $persistance,
            $journalEvenements,
            'annuler_covoiturage',
            'covoiturage',
            'id_covoiturage',
            'annulerCovoiturage',
            'Covoiturage annulé.',
            'covoiturages'
        );

        // 3) Envoyer les emails uniquement si l'annulation a réussi
        if ($resultat['succes'] === true) {
            foreach ($emailsPassagers as $emailDestinataire) {
                $emailDestinataire = trim((string) $emailDestinataire);
                if ($emailDestinataire === '') {
                    continue;
                }

                try {
                    $message = (new Email())
                        ->from('ne-pas-repondre@ecoride.fr')
                        ->to($emailDestinataire)
                        ->subject('EcoRide - Annulation de votre covoiturage')
                        ->text(
                            "Bonjour,\n\n" .
                            "Le covoiturage auquel vous étiez inscrit a été annulé par le chauffeur.\n" .
                            "Vous pouvez consulter votre historique sur EcoRide.\n\n" .
                            "EcoRide"
                        );

                    $serviceCourriel->send($message);
                } catch (Throwable $e) {
                    $this->logErreur(
                        $journalEvenements,
                        'courriel_annulation_envoi_erreur',
                        $idUtilisateur,
                        $e,
                        'annuler_covoiturage',
                        'covoiturage',
                        $idCovoiturage
                    );
                }
            }
        }

        return $resultat['reponse'];
    }



    #[Route('/historique/demarrer-covoiturage', name: 'demarrer_covoiturage', methods: ['POST'])]
    public function demarrerCovoiturage(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): RedirectResponse {
        $resultat = $this->executerActionSimple(
            $requete,
            $sessionUtilisateur,
            $persistance,
            $journalEvenements,
            'demarrer_covoiturage',
            'covoiturage',
            'id_covoiturage',
            'demarrerCovoiturage',
            'Covoiturage démarré.',
            'covoiturages'
        );

        return $resultat['reponse'];
    }

    #[Route('/historique/terminer-covoiturage', name: 'terminer_covoiturage', methods: ['POST'])]
    public function terminerCovoiturage(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements,
        MailerInterface $serviceCourriel
    ): RedirectResponse {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        // Récupération de l'id immédiate 
        $idCovoiturage = (int) $requete->request->get('id_covoiturage', 0);

        // Préparer la liste des emails avant
        $emailsParticipants = [];
        if ($idCovoiturage > 0) {
            try {
                $emailsParticipants = $persistance->listerEmailsParticipants($idUtilisateur, $idCovoiturage);
            } catch (Throwable $e) {
                $journalEvenements->enregistrerErreur(
                    'courriel_terminaison_preparation_erreur',
                    'covoiturage',
                    $idCovoiturage,
                    $e,
                    ['id_utilisateur' => $idUtilisateur]
                );
                $emailsParticipants = [];
            }
        }

        // Terminer via le helper (CSRF + id + service + logs)
        $resultat = $this->executerActionSimple(
            $requete,
            $sessionUtilisateur,
            $persistance,
            $journalEvenements,
            'terminer_covoiturage',
            'covoiturage',
            'id_covoiturage',
            'terminerCovoiturage',
            'Covoiturage terminé.',
            'covoiturages'
        );

        // Envoyer les emails uniquement si succès
        if ($resultat['succes'] === true) {
            foreach ($emailsParticipants as $emailDestinataire) {
                $emailDestinataire = trim((string) $emailDestinataire);
                if ($emailDestinataire === '') {
                    continue;
                }

                try {
                    $message = (new Email())
                        ->from('ne-pas-repondre@ecoride.fr')
                        ->to($emailDestinataire)
                        ->subject('EcoRide - Trajet terminé : merci de valider votre participation')
                        ->text(
                            "Bonjour,\n\n"
                            . "Le chauffeur a indiqué être arrivé à destination.\n"
                            . "Merci de vous rendre sur EcoRide > Mon historique > Mes participations\n"
                            . "pour valider si tout s’est bien passé et, si vous le souhaitez, laisser une note et un avis.\n\n"
                            . "EcoRide"
                        );

                    $serviceCourriel->send($message);
                } catch (Throwable $e) {
                    $journalEvenements->enregistrerErreur(
                        'courriel_terminaison_envoi_erreur',
                        'covoiturage',
                        $idCovoiturage,
                        $e,
                        [
                            'id_utilisateur' => $idUtilisateur,
                            'destinataire' => $emailDestinataire,
                        ]
                    );
                }
            }
        }

        return $resultat['reponse'];
    }

    #[Route('/historique/declarer-incident', name: 'declarer_incident', methods: ['POST'])]
    public function declarerIncident(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): RedirectResponse {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        if (!$this->isCsrfTokenValid('declarer_incident', (string) $requete->request->get('_token'))) {
            $this->logRefus($journalEvenements, 'incident_refuse', 'csrf_invalide', $idUtilisateur, 'covoiturage', 0);
            $this->addFlash('erreur', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
        }

        $idCovoiturage = (int) $requete->request->get('id_covoiturage', 0);
        $commentaire = trim((string) $requete->request->get('incident_commentaire', ''));

        if ($idCovoiturage <= 0 || $commentaire === '') {
            $this->logRefus($journalEvenements, 'incident_refuse', 'parametres_invalides', $idUtilisateur, 'covoiturage', $idCovoiturage);
            $this->addFlash('erreur', 'Covoiturage ou commentaire invalide.');
            return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
        }

        try {
            $persistance->declarerIncident($idUtilisateur, $idCovoiturage, $commentaire);
            $this->logSucces($journalEvenements, 'incident_declare', $idUtilisateur, 'covoiturage', $idCovoiturage);
            $this->addFlash('succes', 'Incident déclaré.');
        } catch (RuntimeException $e) {
            $this->logRefus($journalEvenements, 'incident_refuse', 'regle_metier', $idUtilisateur, 'covoiturage', $idCovoiturage, $e->getMessage());
            $this->addFlash('erreur', $e->getMessage());
        } catch (Throwable $e) {
            $this->logErreur($journalEvenements, 'incident_erreur', $idUtilisateur, $e, 'declarer_incident', 'covoiturage', $idCovoiturage);
            $this->addFlash('erreur', 'Erreur technique : impossible de déclarer un incident.');
        }

        return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
    }
    /*
     HELPER pour les actions "id + csrf + service"
     - renvoie aussi si l'action a réussi
   */

    private function executerActionSimple(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements,
        string $cleCsrf,
        string $entiteJournal,
        string $cleIdPost,
        string $methodeService,
        string $messageSucces,
        string $ongletRetour
    ): array {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        if (!$this->isCsrfTokenValid($cleCsrf, (string) $requete->request->get('_token'))) {
            $this->logRefus($journalEvenements, $methodeService . '_refuse', 'csrf_invalide', $idUtilisateur, $entiteJournal, 0);
            $this->addFlash('erreur', 'Jeton CSRF invalide.');

            return [
                'succes' => false,
                'id_entite' => 0,
                'reponse' => $this->redirectToRoute('historique', ['onglet' => $ongletRetour]),
            ];
        }

        $idEntite = (int) $requete->request->get($cleIdPost, 0);
        if ($idEntite <= 0) {
            $this->logRefus($journalEvenements, $methodeService . '_refuse', 'id_invalide', $idUtilisateur, $entiteJournal, $idEntite);
            $this->addFlash('erreur', 'Identifiant invalide.');

            return [
                'succes' => false,
                'id_entite' => $idEntite,
                'reponse' => $this->redirectToRoute('historique', ['onglet' => $ongletRetour]),
            ];
        }

        try {
            $persistance->$methodeService($idUtilisateur, $idEntite);

            $this->logSucces($journalEvenements, $methodeService . '_succes', $idUtilisateur, $entiteJournal, $idEntite);
            $this->addFlash('succes', $messageSucces);

            return [
                'succes' => true,
                'id_entite' => $idEntite,
                'reponse' => $this->redirectToRoute('historique', ['onglet' => $ongletRetour]),
            ];
        } catch (RuntimeException $e) {
            $this->logRefus($journalEvenements, $methodeService . '_refuse', 'regle_metier', $idUtilisateur, $entiteJournal, $idEntite, $e->getMessage());
            $this->addFlash('erreur', $e->getMessage());
        } catch (Throwable $e) {
            $this->logErreur($journalEvenements, $methodeService . '_erreur', $idUtilisateur, $e, $methodeService, $entiteJournal, $idEntite);
            $this->addFlash('erreur', 'Erreur technique : action impossible.');
        }

        return [
            'succes' => false,
            'id_entite' => $idEntite,
            'reponse' => $this->redirectToRoute('historique', ['onglet' => $ongletRetour]),
        ];
    }

    /* JOURNAL */

    private function logRefus(
        JournalEvenements $journal,
        string $action,
        string $raison,
        int $idUtilisateur,
        string $entite,
        int $idEntite,
        string $message = ''
    ): void {
        $journal->enregistrer($action, $entite, $idEntite, [
            'id_utilisateur' => $idUtilisateur,
            'raison' => $raison,
            'message' => $message,
        ]);
    }

    private function logSucces(
        JournalEvenements $journal,
        string $action,
        int $idUtilisateur,
        string $entite,
        int $idEntite
    ): void {
        $journal->enregistrer($action, $entite, $idEntite, [
            'id_utilisateur' => $idUtilisateur,
        ]);
    }

    private function logErreur(
        JournalEvenements $journal,
        string $action,
        int $idUtilisateur,
        Throwable $e,
        string $contexteAction,
        string $entite,
        int $idEntite
    ): void {
        $journal->enregistrerErreur($action, $entite, $idEntite, $e, [
            'id_utilisateur' => $idUtilisateur,
            'action' => $contexteAction,
        ]);
    }
}
