<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EnvoiCourriels;
use App\Service\JournalEvenements;
use App\Service\PersistanceCovoituragePostgresql;
use App\Service\PersistanceHistoriquePostgresql;
use App\Service\SessionUtilisateur;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class HistoriqueController extends AbstractController
{
    /**
     * Affiche l'interface principale de l'historique utilisateur.
     *
     * Cette page fonctionne avec deux onglets :
     * les covoiturages publiés par l'utilisateur
     * et les participations auxquelles il a pris part.
     *
     * L'onglet demandé est lu dans l'URL.
     * Une seule liste est chargée à la fois pour éviter
     * des lectures inutiles en base de données.
     */
    #[Route('/historique', name: 'historique', methods: ['GET'])]
    public function index(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance
    ): Response {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        $onglet = (string) $requete->query->get('onglet', 'covoiturages');
        if (!in_array($onglet, ['covoiturages', 'participations'], true)) {
            $onglet = 'covoiturages';
        }

        $covoituragesPublies = [];
        $participations = [];

        if ($onglet === 'covoiturages') {
            $covoituragesPublies = $persistance->listerCovoituragesPublies($idUtilisateur);
        } else {
            $participations = $persistance->listerParticipations($idUtilisateur);
        }

        return $this->render('historique/index.html.twig', [
            'onglet' => $onglet,
            'covoiturages_publies' => $covoituragesPublies,
            'participations' => $participations,
        ]);
    }

    /**
     * Affiche le formulaire de déclaration d'incident.
     *
     * Cette page est réservée au chauffeur concerné.
     * Le contrôleur vérifie ici l'accès au parcours,
     * puis laisse Twig afficher le formulaire.
     *
     * Si l'accès est refusé, l'événement retenu dans MongoDB
     * est `incident_refuse`.
     */
    #[Route('/historique/incident/{id}', name: 'incident_formulaire', methods: ['GET'])]
    public function afficherFormulaireIncident(
        int $id,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): Response {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        if ($id <= 0) {
            $this->addFlash('erreur', 'Trajet invalide.');
            $this->logRefus(
                $journalEvenements,
                'incident_refuse',
                'id_invalide',
                $idUtilisateur,
                'covoiturage',
                $id
            );

            return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
        }

        if (!$persistance->peutDeclarerIncident($idUtilisateur, $id)) {
            $this->addFlash('erreur', 'Accès refusé ou trajet non éligible à un incident.');
            $this->logRefus(
                $journalEvenements,
                'incident_refuse',
                'acces_interdit',
                $idUtilisateur,
                'covoiturage',
                $id
            );

            return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
        }

        return $this->render('historique/incident.html.twig', [
            'id_covoiturage' => $id,
        ]);
    }

    /**
     * Affiche le formulaire de satisfaction.
     *
     * Cette page est réservée au passager concerné
     * lorsque le trajet permet encore ce dépôt.
     *
     * Si l'accès est refusé, l'événement retenu dans MongoDB
     * est `satisfaction_refusee`.
     */
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
            $this->logRefus(
                $journalEvenements,
                'satisfaction_refusee',
                'id_invalide',
                $idUtilisateur,
                'covoiturage',
                $id
            );

            return $this->redirectToRoute('historique', ['onglet' => 'participations']);
        }

        if (!$persistance->peutDonnerAvis($idUtilisateur, $id)) {
            $this->addFlash('erreur', 'Accès refusé ou avis déjà donné.');
            $this->logRefus(
                $journalEvenements,
                'satisfaction_refusee',
                'acces_interdit',
                $idUtilisateur,
                'covoiturage',
                $id
            );

            return $this->redirectToRoute('historique', ['onglet' => 'participations']);
        }

        return $this->render('historique/satisfaction.html.twig', [
            'id_covoiturage' => $id,
            'ancien' => [],
            'erreurs' => [],
        ]);
    }

    /**
     * Annule une participation.
     *
     * Cette action suit un flux simple :
     * contrôle du jeton CSRF,
     * lecture de l'identifiant,
     * appel à PostgreSQL,
     * message flash
     * puis redirection.
     *
     * En cas de succès, l'événement retenu est `participation_annulee`.
     */
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
            'participation_annulee',
            'Participation annulée.',
            'participations'
        );

        return $resultat['reponse'];
    }

    /**
     * Enregistre la satisfaction d'un passager.
     *
     * Le formulaire demande une note et un commentaire.
     * Une fois validé, le contrôleur délègue l'écriture à PostgreSQL.
     *
     * Ce parcours produit plusieurs informations métier utiles :
     * le parcours de satisfaction a abouti
     * et un avis a réellement été créé.
     *
     * Les événements retenus sont donc :
     * - `satisfaction_enregistree`
     * - `avis_depose`
     *
     * Les refus restent journalisés sous `satisfaction_refusee`
     * et les erreurs techniques sous `satisfaction_erreur`.
     */
    #[Route('/historique/enregistrer-satisfaction', name: 'enregistrer_satisfaction', methods: ['POST'])]
    public function enregistrerSatisfaction(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistance,
        JournalEvenements $journalEvenements
    ): Response {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        if (!$this->isCsrfTokenValid('enregistrer_satisfaction', (string) $requete->request->get('_token'))) {
            $this->logRefus(
                $journalEvenements,
                'satisfaction_refusee',
                'csrf_invalide',
                $idUtilisateur,
                'covoiturage',
                0
            );
            $this->addFlash('erreur', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('historique', ['onglet' => 'participations']);
        }

        $idCovoiturage = (int) $requete->request->get('id_covoiturage', 0);
        $note = (int) $requete->request->get('note', 0);
        $commentaire = trim((string) $requete->request->get('commentaire', ''));

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

        if (!empty($erreurs)) {
            $this->logRefus(
                $journalEvenements,
                'satisfaction_refusee',
                'parametres_invalides',
                $idUtilisateur,
                'covoiturage',
                $idCovoiturage
            );

            return $this->render('historique/satisfaction.html.twig', [
                'id_covoiturage' => $idCovoiturage,
                'ancien' => [
                    'note' => $note > 0 ? $note : '',
                    'commentaire' => $commentaire,
                ],
                'erreurs' => $erreurs,
            ]);
        }

        if (!$persistance->peutDonnerAvis($idUtilisateur, $idCovoiturage)) {
            $this->addFlash('erreur', 'Accès refusé ou avis déjà donné.');
            $this->logRefus(
                $journalEvenements,
                'satisfaction_refusee',
                'acces_interdit',
                $idUtilisateur,
                'covoiturage',
                $idCovoiturage
            );

            return $this->redirectToRoute('historique', ['onglet' => 'participations']);
        }

        try {
            $persistance->enregistrerSatisfaction($idUtilisateur, $idCovoiturage, $note, $commentaire);

            $this->logSucces(
                $journalEvenements,
                'satisfaction_enregistree',
                $idUtilisateur,
                'covoiturage',
                $idCovoiturage
            );

            $this->logSucces(
                $journalEvenements,
                'avis_depose',
                $idUtilisateur,
                'covoiturage',
                $idCovoiturage
            );

            $this->addFlash('succes', 'Merci pour votre avis !');
        } catch (RuntimeException $e) {
            $this->logRefus(
                $journalEvenements,
                'satisfaction_refusee',
                'regle_metier',
                $idUtilisateur,
                'covoiturage',
                $idCovoiturage,
                $e->getMessage()
            );
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
            $this->logErreur(
                $journalEvenements,
                'satisfaction_erreur',
                $idUtilisateur,
                $e,
                'enregistrer_satisfaction',
                'covoiturage',
                $idCovoiturage
            );
            $this->addFlash('erreur', 'Erreur technique : enregistrement impossible.');
        }

        return $this->redirectToRoute('historique', ['onglet' => 'participations']);
    }

    /**
     * Annule un covoiturage publié par l'utilisateur connecté.
     *
     * Si l'action réussit, l'événement retenu est `covoiturage_annule`.
     * Les courriels sont envoyés après l'annulation
     * et restent secondaires par rapport à l'action principale.
     */
    #[Route('/historique/annuler-covoiturage', name: 'annuler_covoiturage', methods: ['POST'])]
    public function annulerCovoiturage(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistanceHistorique,
        PersistanceCovoituragePostgresql $persistanceCovoiturage,
        EnvoiCourriels $envoiCourriels,
        JournalEvenements $journalEvenements
    ): RedirectResponse {
        $idCovoiturage = (int) $requete->request->get('id_covoiturage', 0);
        $jeton = (string) $requete->request->get('_token');

        /*
         * Les participants et le résumé du covoiturage sont lus avant l'annulation.
         * Après l'annulation, les participations actives peuvent avoir changé.
         */
        $participants = [];
        $covoiturage = null;

        if ($idCovoiturage > 0 && $this->isCsrfTokenValid('annuler_covoiturage', $jeton)) {
            $participants = $persistanceCovoiturage->listerParticipantsPourCourriel($idCovoiturage);
            $covoiturage = $persistanceCovoiturage->obtenirResumeCovoituragePourCourriel($idCovoiturage);
        }

        $resultat = $this->executerActionSimple(
            $requete,
            $sessionUtilisateur,
            $persistanceHistorique,
            $journalEvenements,
            'annuler_covoiturage',
            'covoiturage',
            'id_covoiturage',
            'annulerCovoiturage',
            'covoiturage_annule',
            'Covoiturage annulé.',
            'covoiturages'
        );

        if ($resultat['succes'] === true && $covoiturage !== null) {
            try {
                $envoiCourriels->envoyerAnnulationCovoiturage($participants, $covoiturage);
            } catch (TransportExceptionInterface) {
                /*
                 * Le courriel reste un traitement complémentaire.
                 * L'annulation du trajet reste valide même si l'envoi échoue.
                 */
            }
        }

        return $resultat['reponse'];
    }

    /**
     * Termine un covoiturage publié par l'utilisateur connecté.
     *
     * Si l'action réussit, l'événement retenu est `covoiturage_termine`.
     * Le courriel de demande de validation est envoyé ensuite.
     */
    #[Route('/historique/terminer-covoiturage', name: 'terminer_covoiturage', methods: ['POST'])]
    public function terminerCovoiturage(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistanceHistorique,
        PersistanceCovoituragePostgresql $persistanceCovoiturage,
        EnvoiCourriels $envoiCourriels,
        JournalEvenements $journalEvenements
    ): RedirectResponse {
        $idCovoiturage = (int) $requete->request->get('id_covoiturage', 0);

        $resultat = $this->executerActionSimple(
            $requete,
            $sessionUtilisateur,
            $persistanceHistorique,
            $journalEvenements,
            'terminer_covoiturage',
            'covoiturage',
            'id_covoiturage',
            'terminerCovoiturage',
            'covoiturage_termine',
            'Covoiturage terminé.',
            'covoiturages'
        );

        if ($resultat['succes'] === true) {
            $participants = $persistanceCovoiturage->listerParticipantsPourCourriel($idCovoiturage);
            $covoiturage = $persistanceCovoiturage->obtenirResumeCovoituragePourCourriel($idCovoiturage);

            if ($covoiturage !== null) {
                try {
                    $envoiCourriels->envoyerDemandeValidationTrajet($participants, $covoiturage);
                } catch (TransportExceptionInterface) {
                    /*
                     * Le trajet reste terminé même si le courriel échoue.
                     */
                }
            }
        }

        return $resultat['reponse'];
    }

    /**
     * Démarre un covoiturage publié par l'utilisateur connecté.
     *
     * Si l'action réussit, l'événement retenu est `covoiturage_demarre`.
     */
    #[Route('/historique/demarrer-covoiturage', name: 'demarrer_covoiturage', methods: ['POST'])]
    public function demarrerCovoiturage(
        Request $requete,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceHistoriquePostgresql $persistanceHistorique,
        JournalEvenements $journalEvenements
    ): RedirectResponse {
        $resultat = $this->executerActionSimple(
            $requete,
            $sessionUtilisateur,
            $persistanceHistorique,
            $journalEvenements,
            'demarrer_covoiturage',
            'covoiturage',
            'id_covoiturage',
            'demarrerCovoiturage',
            'covoiturage_demarre',
            'Covoiturage démarré.',
            'covoiturages'
        );

        return $resultat['reponse'];
    }

    /**
     * Déclare un incident sur un covoiturage.
     *
     * Trois événements peuvent être retenus ici :
     * `incident_declare`,
     * `incident_refuse`
     * et `incident_erreur`.
     */
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
            $this->logRefus(
                $journalEvenements,
                'incident_refuse',
                'csrf_invalide',
                $idUtilisateur,
                'covoiturage',
                0
            );
            $this->addFlash('erreur', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
        }

        $idCovoiturage = (int) $requete->request->get('id_covoiturage', 0);
        $commentaire = trim((string) $requete->request->get('incident_commentaire', ''));

        if ($idCovoiturage <= 0 || $commentaire === '') {
            $this->logRefus(
                $journalEvenements,
                'incident_refuse',
                'parametres_invalides',
                $idUtilisateur,
                'covoiturage',
                $idCovoiturage
            );
            $this->addFlash('erreur', 'Covoiturage ou commentaire invalide.');

            return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
        }

        try {
            $persistance->declarerIncident($idUtilisateur, $idCovoiturage, $commentaire);

            $this->logSucces(
                $journalEvenements,
                'incident_declare',
                $idUtilisateur,
                'covoiturage',
                $idCovoiturage
            );

            $this->addFlash('succes', 'Incident déclaré.');
        } catch (RuntimeException $e) {
            $this->logRefus(
                $journalEvenements,
                'incident_refuse',
                'regle_metier',
                $idUtilisateur,
                'covoiturage',
                $idCovoiturage,
                $e->getMessage()
            );
            $this->addFlash('erreur', $e->getMessage());
        } catch (Throwable $e) {
            $this->logErreur(
                $journalEvenements,
                'incident_erreur',
                $idUtilisateur,
                $e,
                'declarer_incident',
                'covoiturage',
                $idCovoiturage
            );
            $this->addFlash('erreur', 'Erreur technique : impossible de déclarer un incident.');
        }

        return $this->redirectToRoute('historique', ['onglet' => 'covoiturages']);
    }

    /**
     * Exécute une action simple fondée sur un identifiant reçu en POST.
     *
     * Ce helper sert aux actions qui ont le même enchaînement :
     * vérification du jeton CSRF,
     * lecture de l'identifiant,
     * appel d'une méthode de persistance,
     * message flash
     * et redirection.
     *
     * Dans ce helper, seule la réussite est journalisée.
     * Les refus et erreurs de ces actions ne font pas partie
     * des événements retenus dans ce fichier.
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
        string $evenementSucces,
        string $messageSucces,
        string $ongletRetour
    ): array {
        $utilisateur = $sessionUtilisateur->exigerUtilisateurConnecte();
        $idUtilisateur = (int) $utilisateur['id_utilisateur'];

        if (!$this->isCsrfTokenValid($cleCsrf, (string) $requete->request->get('_token'))) {
            $this->addFlash('erreur', 'Jeton CSRF invalide.');

            return [
                'succes' => false,
                'id_entite' => 0,
                'reponse' => $this->redirectToRoute('historique', ['onglet' => $ongletRetour]),
            ];
        }

        $idEntite = (int) $requete->request->get($cleIdPost, 0);
        if ($idEntite <= 0) {
            $this->addFlash('erreur', 'Identifiant invalide.');

            return [
                'succes' => false,
                'id_entite' => $idEntite,
                'reponse' => $this->redirectToRoute('historique', ['onglet' => $ongletRetour]),
            ];
        }

        try {
            $persistance->$methodeService($idUtilisateur, $idEntite);

            $this->logSucces($journalEvenements, $evenementSucces, $idUtilisateur, $entiteJournal, $idEntite);
            $this->addFlash('succes', $messageSucces);

            return [
                'succes' => true,
                'id_entite' => $idEntite,
                'reponse' => $this->redirectToRoute('historique', ['onglet' => $ongletRetour]),
            ];
        } catch (RuntimeException $e) {
            $this->addFlash('erreur', $e->getMessage());
        } catch (Throwable) {
            $this->addFlash('erreur', 'Erreur technique : action impossible.');
        }

        return [
            'succes' => false,
            'id_entite' => $idEntite,
            'reponse' => $this->redirectToRoute('historique', ['onglet' => $ongletRetour]),
        ];
    }

    /**
     * Journalise un refus dans MongoDB.
     *
     * `JournalEvenements` refuse les enregistrements
     * si `id_entite` vaut 0 ou moins.
     *
     * Quand l'identifiant métier visé existe,
     * il devient l'entité principale du document.
     * Quand cet identifiant n'est pas exploitable,
     * l'utilisateur connecté devient l'entité principale
     * et la cible initiale est déplacée dans les données complémentaires.
     *
     * Cela permet de garder une trace exploitable
     * même quand l'action échoue avant d'avoir un identifiant valide.
     */
    private function logRefus(
        JournalEvenements $journal,
        string $action,
        string $raison,
        int $idUtilisateur,
        string $entite,
        int $idEntite,
        string $message = ''
    ): void {
        if ($idEntite > 0) {
            $journal->enregistrer($action, $entite, $idEntite, [
                'id_utilisateur' => $idUtilisateur,
                'raison' => $raison,
                'message' => $message,
            ]);

            return;
        }

        $journal->enregistrer($action, 'utilisateur', $idUtilisateur, [
            'raison' => $raison,
            'message' => $message,
            'entite_cible' => $entite,
            'id_entite_cible' => $idEntite,
        ]);
    }

    /**
     * Journalise un succès dans MongoDB.
     *
     * Le document garde l'entité principale concernée
     * ainsi que l'utilisateur connecté.
     */
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

    /**
     * Journalise une erreur technique dans MongoDB.
     *
     * Comme pour les refus, si l'identifiant métier n'est pas exploitable,
     * l'utilisateur connecté devient l'entité principale du document.
     *
     * L'exception est transmise à `enregistrerErreur()`
     * pour conserver les informations techniques utiles au diagnostic.
     */
    private function logErreur(
        JournalEvenements $journal,
        string $action,
        int $idUtilisateur,
        Throwable $e,
        string $contexteAction,
        string $entite,
        int $idEntite
    ): void {
        if ($idEntite > 0) {
            $journal->enregistrerErreur($action, $entite, $idEntite, $e, [
                'id_utilisateur' => $idUtilisateur,
                'action' => $contexteAction,
            ]);

            return;
        }

        $journal->enregistrerErreur($action, 'utilisateur', $idUtilisateur, $e, [
            'action' => $contexteAction,
            'entite_cible' => $entite,
            'id_entite_cible' => $idEntite,
        ]);
    }
}