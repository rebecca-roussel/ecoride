<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceVoiturePostgresql;
use App\Service\SessionUtilisateur;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GererVehiculesController extends AbstractController
{
    /*
      PLAN (GererVehiculesController) :

      1) Accès sécurisé
         - utiliser SessionUtilisateur (source unique de vérité)
         - si pas connecté : rediriger vers la connexion

      2) Afficher la page "Gérer mes véhicules"
         - demander la liste des véhicules à PersistanceVoiturePostgresql
         - calculer l’ancienneté pour l’affichage
         - envoyer les données à Twig
         - tracer l’ouverture de page dans MongoDB

      3) Supprimer un véhicule (suppression logique)
         - protéger l’action avec un jeton CSRF
         - tracer la demande de suppression dans MongoDB
         - appeler la suppression logique via PersistanceVoiturePostgresql
         - afficher un message (succès ou erreur) puis rediriger

      4) Ajouter un véhicule
         - afficher le formulaire (GET)
         - traiter l'ajout (POST)

      5) Modifier un véhicule
         - afficher le formulaire pré-rempli (GET)
         - traiter la modification (POST)
    */

    public function __construct(
        // Ici je reçois les outils services via l’injection de dépendances.
        private PersistanceVoiturePostgresql $persistanceVoiturePostgresql,
        private JournalEvenements $journalEvenements,
        private SessionUtilisateur $sessionUtilisateur
    ) {
    }

    #[Route('/espace/vehicules', name: 'gerer_vehicules', methods: ['GET'])]
    public function index(): Response
    {
        // Sécurité : si l’utilisateur n’est pas connecté, je ne laisse pas accéder à l’espace.
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        // Je récupère l’id utilisateur depuis la session.
        // Si jamais il est absent, on redirige aussi.
        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        // Trace l’ouverture de la page avec MongoDB.
        $this->journalEvenements->enregistrer(
            'page_ouverte',
            'utilisateur',
            $idUtilisateur,
            [
                'page' => 'gerer_vehicules',
            ]
        );

        // Je récupère la liste des véhicules actifs de cet utilisateur.
        // Actif = suppression : le véhicule existe encore en base mais il est désactivé.
        $vehicules = $this->persistanceVoiturePostgresql->listerVehiculesActifsParUtilisateur($idUtilisateur);

        // Je prépare un champ “ancienneté” pour l’affichage.
        foreach ($vehicules as &$vehicule) {
            // Je force en string pour éviter les surprises.
            $date = (string) ($vehicule['date_1ere_mise_en_circulation'] ?? '');

            // délègue le calcul à un service 
            $vehicule['anciennete_annees'] = $this->persistanceVoiturePostgresql
                ->calculerAncienneteEnAnnees($date, $idUtilisateur);
        }
        unset($vehicule);

        // envoie les données à Twig.
        return $this->render('vehicules/index.html.twig', [
            'vehicules' => $vehicules,
        ]);
    }

    #[Route(
        '/espace/vehicules/{idVoiture}/supprimer',
        name: 'vehicule_supprimer',
        requirements: ['idVoiture' => '\d+'],
        methods: ['POST']
    )]
    public function supprimer(Request $request, int $idVoiture): RedirectResponse
    {
        // Pas connecté = dehors.
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        // On revalide l’id utilisateur.
        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        // Sécurité CSRF :
        // - le formulaire POST envoie un _jeton_csrf
        // - et vérifier que le token correspond bien à l’action + l’id voiture.
        $jeton = (string) $request->request->get('_jeton_csrf', '');
        if (!$this->isCsrfTokenValid('supprimer_vehicule_' . $idVoiture, $jeton)) {
            // Si CSRF invalide : je trace…
            $this->journalEvenements->enregistrer(
                'vehicule_suppression_refusee',
                'voiture',
                $idVoiture,
                [
                    'raison' => 'csrf_invalide',
                    'id_utilisateur' => $idUtilisateur,
                ]
            );

            // Message à l’utilisateur.
            $this->addFlash('erreur', 'Action refusée : jeton invalide.');

            // et on reviens à la page de gestion
            return $this->redirectToRoute('gerer_vehicules');
        }


        $this->journalEvenements->enregistrer(
            'vehicule_suppression_demandee',
            'voiture',
            $idVoiture,
            [
                'id_utilisateur' => $idUtilisateur,
            ]
        );

        try {
            // Désactivation en base et pas de réelles suppressions
            // Et passe aussi l’id utilisateur pour éviter qu’on supprime la voiture d’un autre.
            $this->persistanceVoiturePostgresql->desactiverVehicule($idVoiture, $idUtilisateur);

            $this->addFlash('succes', 'Véhicule supprimé.');
        } catch (RuntimeException $e) {
            // Je capture les erreurs métier.
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('gerer_vehicules');
    }

    #[Route('/espace/vehicules/ajouter', name: 'vehicule_ajouter', methods: ['GET'])]
    public function ajouter(): Response
    {
        // Accès protégé.
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        // Trace l’ouverture de la page “Ajouter véhicule”.
        $this->journalEvenements->enregistrer(
            'page_ouverte',
            'utilisateur',
            $idUtilisateur,
            [
                'page' => 'vehicule_ajouter',
            ]
        );

        // Affiche le formulaire avec des valeurs par défaut
        return $this->render('vehicules/ajouter.html.twig', [
            'est_modification' => false,
            'id_voiture' => null,
            'valeurs' => [
                'immatriculation' => '',
                'date_1ere_mise_en_circulation' => '',
                'marque' => '',
                'couleur' => '',
                'energie' => 'ESSENCE',
                'nb_places' => 1,
            ],
            'erreurs' => [],
        ]);
    }

    #[Route('/espace/vehicules/ajouter', name: 'vehicule_ajouter_traitement', methods: ['POST'])]
    public function ajouterTraitement(Request $request): Response|RedirectResponse
    {
        // Pas connecté renvoie vers connexion.
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        // Vérifie le jeton CSRF de l’ajout
        $jeton = (string) $request->request->get('_jeton_csrf', '');
        if (!$this->isCsrfTokenValid('ajouter_vehicule', $jeton)) {
            $this->journalEvenements->enregistrer(
                'vehicule_ajout_refuse',
                'utilisateur',
                $idUtilisateur,
                [
                    'raison' => 'csrf_invalide',
                ]
            );

            $this->addFlash('erreur', 'Action refusée : jeton invalide.');
            return $this->redirectToRoute('vehicule_ajouter');
        }

        // Champs du formulaire
        $immatriculation = strtoupper(trim((string) $request->request->get('immatriculation', '')));
        $date = trim((string) $request->request->get('date_1ere_mise_en_circulation', ''));
        $marque = trim((string) $request->request->get('marque', ''));
        $couleur = trim((string) $request->request->get('couleur', ''));
        $energie = trim((string) $request->request->get('energie', ''));
        $nbPlaces = (int) $request->request->get('nb_places', 0);

        // Tableau d’erreurs 
        $erreurs = $this->validerFormulaireVehicule($immatriculation, $date, $marque, $couleur, $energie, $nbPlaces);

        // Si erreurs réaffiche le formulaire.
        if (!empty($erreurs)) {
            return $this->render('vehicules/ajouter.html.twig', [
                'est_modification' => false,
                'id_voiture' => null,
                'valeurs' => [
                    'immatriculation' => $immatriculation,
                    'date_1ere_mise_en_circulation' => $date,
                    'marque' => $marque,
                    'couleur' => $couleur,
                    'energie' => $energie,
                    'nb_places' => $nbPlaces,
                ],
                'erreurs' => $erreurs,
            ]);
        }

        try {
            $idVoiture = $this->persistanceVoiturePostgresql->ajouterVehicule(
                $idUtilisateur,
                $immatriculation,
                $date,
                $marque,
                $couleur,
                $energie,
                $nbPlaces
            );

            $this->journalEvenements->enregistrer(
                'vehicule_ajoute',
                'voiture',
                $idVoiture,
                [
                    'id_utilisateur' => $idUtilisateur,
                ]
            );

            $this->addFlash('succes', 'Véhicule ajouté.');
            return $this->redirectToRoute('gerer_vehicules');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            return $this->render('vehicules/ajouter.html.twig', [
                'est_modification' => false,
                'id_voiture' => null,
                'valeurs' => [
                    'immatriculation' => $immatriculation,
                    'date_1ere_mise_en_circulation' => $date,
                    'marque' => $marque,
                    'couleur' => $couleur,
                    'energie' => $energie,
                    'nb_places' => $nbPlaces,
                ],
                'erreurs' => [
                    'formulaire' => $message,
                ],
            ]);
        }
    }

    #[Route(
        '/espace/vehicules/{idVoiture}/modifier',
        name: 'vehicule_modifier',
        requirements: ['idVoiture' => '\d+'],
        methods: ['GET']
    )]
    public function modifier(int $idVoiture): Response|RedirectResponse
    {
        // Accès protégé.
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }


        $vehicule = $this->persistanceVoiturePostgresql->trouverVehiculeParIdEtUtilisateur($idVoiture, $idUtilisateur);
        if ($vehicule === null) {
            $this->addFlash('erreur', 'Véhicule introuvable.');
            return $this->redirectToRoute('gerer_vehicules');
        }


        $this->journalEvenements->enregistrer(
            'page_ouverte',
            'utilisateur',
            $idUtilisateur,
            [
                'page' => 'vehicule_modifier',
                'id_voiture' => $idVoiture,
            ]
        );

        return $this->render('vehicules/ajouter.html.twig', [
            'est_modification' => true,
            'id_voiture' => $idVoiture,
            'valeurs' => [
                'immatriculation' => (string) ($vehicule['immatriculation'] ?? ''),
                'date_1ere_mise_en_circulation' => (string) ($vehicule['date_1ere_mise_en_circulation'] ?? ''),
                'marque' => (string) ($vehicule['marque'] ?? ''),
                'couleur' => (string) ($vehicule['couleur'] ?? ''),
                'energie' => (string) ($vehicule['energie'] ?? 'ESSENCE'),
                'nb_places' => (int) ($vehicule['nb_places'] ?? 1),
            ],
            'erreurs' => [],
        ]);
    }

    #[Route(
        '/espace/vehicules/{idVoiture}/modifier',
        name: 'vehicule_modifier_traitement',
        requirements: ['idVoiture' => '\d+'],
        methods: ['POST']
    )]
    public function modifierTraitement(Request $request, int $idVoiture): Response|RedirectResponse
    {
        // Accès protégé.
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        // Vérifie le jeton CSRF de la modification.
        $jeton = (string) $request->request->get('_jeton_csrf', '');
        if (!$this->isCsrfTokenValid('modifier_vehicule_' . $idVoiture, $jeton)) {
            $this->addFlash('erreur', 'Action refusée : jeton invalide.');
            return $this->redirectToRoute('vehicule_modifier', ['idVoiture' => $idVoiture]);
        }

        // Récupère les champs du formulaire.
        $immatriculation = strtoupper(trim((string) $request->request->get('immatriculation', '')));
        $date = trim((string) $request->request->get('date_1ere_mise_en_circulation', ''));
        $marque = trim((string) $request->request->get('marque', ''));
        $couleur = trim((string) $request->request->get('couleur', ''));
        $energie = trim((string) $request->request->get('energie', ''));
        $nbPlaces = (int) $request->request->get('nb_places', 0);

        $erreurs = $this->validerFormulaireVehicule($immatriculation, $date, $marque, $couleur, $energie, $nbPlaces);

        if (!empty($erreurs)) {
            return $this->render('vehicules/ajouter.html.twig', [
                'est_modification' => true,
                'id_voiture' => $idVoiture,
                'valeurs' => [
                    'immatriculation' => $immatriculation,
                    'date_1ere_mise_en_circulation' => $date,
                    'marque' => $marque,
                    'couleur' => $couleur,
                    'energie' => $energie,
                    'nb_places' => $nbPlaces,
                ],
                'erreurs' => $erreurs,
            ]);
        }

        try {
            // Sécurité : je revalide que le véhicule appartient bien à l’utilisateur.
            $vehicule = $this->persistanceVoiturePostgresql->trouverVehiculeParIdEtUtilisateur($idVoiture, $idUtilisateur);
            if ($vehicule === null) {
                $this->addFlash('erreur', 'Véhicule introuvable.');
                return $this->redirectToRoute('gerer_vehicules');
            }

            $this->persistanceVoiturePostgresql->modifierVehicule(
                $idVoiture,
                $idUtilisateur,
                $immatriculation,
                $date,
                $marque,
                $couleur,
                $energie,
                $nbPlaces
            );

            $this->journalEvenements->enregistrer(
                'vehicule_modifie',
                'voiture',
                $idVoiture,
                [
                    'id_utilisateur' => $idUtilisateur,
                ]
            );

            $this->addFlash('succes', 'Véhicule modifié.');
            return $this->redirectToRoute('gerer_vehicules');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            return $this->render('vehicules/ajouter.html.twig', [
                'est_modification' => true,
                'id_voiture' => $idVoiture,
                'valeurs' => [
                    'immatriculation' => $immatriculation,
                    'date_1ere_mise_en_circulation' => $date,
                    'marque' => $marque,
                    'couleur' => $couleur,
                    'energie' => $energie,
                    'nb_places' => $nbPlaces,
                ],
                'erreurs' => [
                    'formulaire' => $message,
                ],
            ]);
        }
    }

    private function validerFormulaireVehicule(
        string $immatriculation,
        string $date,
        string $marque,
        string $couleur,
        string $energie,
        int $nbPlaces
    ): array {
        $erreurs = [];

        // Validation côté serveur.
        if ($immatriculation === '' || !preg_match('/^[A-Z0-9]+$/', $immatriculation)) {
            $erreurs['immatriculation'] = 'Immatriculation invalide (majuscules + chiffres, sans espaces).';
        }
        if ($date === '') {
            $erreurs['date_1ere_mise_en_circulation'] = 'Date obligatoire.';
        }
        if ($marque === '') {
            $erreurs['marque'] = 'Marque obligatoire.';
        }
        if ($couleur === '') {
            $erreurs['couleur'] = 'Couleur obligatoire.';
        }
        if (!in_array($energie, ['ESSENCE', 'DIESEL', 'ETHANOL', 'HYBRIDE', 'ELECTRIQUE'], true)) {
            $erreurs['energie'] = 'Énergie invalide.';
        }
        if ($nbPlaces < 1 || $nbPlaces > 4) {
            $erreurs['nb_places'] = 'Le nombre de places doit être entre 1 et 4.';
        }

        return $erreurs;
    }
}
