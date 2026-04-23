<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceVoiturePostgresql;
use App\Service\SessionUtilisateur;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de gestion des voitures utilisateur.
 *
 * Cette classe gère l'affichage de la liste des voitures,
 * l'ouverture du formulaire d'ajout,
 * l'enregistrement d'une nouvelle voiture,
 * l'ouverture du formulaire de modification,
 * l'enregistrement des modifications
 * et la désactivation d'une voiture.
 *
 * Le contrôleur garde ici tout ce qui relève du parcours web :
 * contrôle d'accès,
 * lecture des champs envoyés par le formulaire,
 * vérification du jeton CSRF,
 * messages flash,
 * rendu Twig et redirections.
 *
 * Les accès à PostgreSQL sont délégués à `PersistanceVoiturePostgresql`.
 * La journalisation MongoDB est utilisée ici seulement
 * pour les événements significatifs retenus sur le domaine voiture.
 */
final class GererVehiculesController extends AbstractController
{
    /**
     * Initialise le contrôleur avec ses dépendances.
     *
     * @param PersistanceVoiturePostgresql $persistanceVoiturePostgresql
     *        Service de lecture et d'écriture PostgreSQL pour les voitures.
     * @param JournalEvenements $journalEvenements
     *        Service de journalisation MongoDB.
     * @param SessionUtilisateur $sessionUtilisateur
     *        Service qui permet de lire l'utilisateur connecté.
     */
    public function __construct(
        private PersistanceVoiturePostgresql $persistanceVoiturePostgresql,
        private JournalEvenements $journalEvenements,
        private SessionUtilisateur $sessionUtilisateur
    ) {
    }

    /**
     * Affiche la liste des voitures actives de l'utilisateur connecté.
     *
     * La méthode lit les voitures actives liées à l'utilisateur,
     * puis ajoute une ancienneté en années pour simplifier l'affichage dans Twig.
     *
     * @return Response Réponse HTML rendue par Twig ou redirection.
     */
    #[Route('/espace/vehicules', name: 'gerer_vehicules', methods: ['GET'])]
    public function index(): Response
    {
        /*
         * Cette page est réservée à un utilisateur connecté.
         */
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        /*
         * Les voitures sont lues depuis PostgreSQL.
         * La persistance renvoie ici les données utiles à l'affichage.
         */
        $vehicules = $this->persistanceVoiturePostgresql->listerVehiculesActifsParUtilisateur($idUtilisateur);

        /*
         * Chaque voiture reçoit une ancienneté calculée à partir
         * de sa date de première mise en circulation.
         *
         * Ce calcul ne dépend pas de la base de données.
         * Il sert uniquement à enrichir les données avant l'affichage.
         */
        foreach ($vehicules as &$vehicule) {
            $date = (string) ($vehicule['date_1ere_mise_en_circulation'] ?? '');

            $vehicule['anciennete_annees'] = $this->calculerAncienneteEnAnnees($date);
        }
        unset($vehicule);

        return $this->render('vehicules/index.html.twig', [
            'vehicules' => $vehicules,
        ]);
    }

    /**
     * Désactive une voiture appartenant à l'utilisateur connecté.
     *
     * La suppression affichée côté interface correspond ici
     * à une désactivation logique :
     * la voiture reste enregistrée en base,
     * mais elle n'est plus active.
     *
     * @param Request $request Requête HTTP contenant le jeton CSRF.
     * @param int $idVoiture Identifiant de la voiture.
     *
     * @return RedirectResponse Redirection vers la liste des voitures.
     */
    #[Route(
        '/espace/vehicules/{idVoiture}/supprimer',
        name: 'vehicule_supprimer',
        requirements: ['idVoiture' => '\d+'],
        methods: ['POST']
    )]
    public function supprimer(Request $request, int $idVoiture): RedirectResponse
    {
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        /*
         * Le jeton CSRF protège l'action contre une soumission frauduleuse.
         * Il permet de vérifier que le formulaire vient bien de l'application.
         */
        $jeton = (string) $request->request->get('_jeton_csrf', '');
        if (!$this->isCsrfTokenValid('supprimer_vehicule_' . $idVoiture, $jeton)) {
            $this->addFlash('erreur', 'Action refusée : jeton invalide.');

            return $this->redirectToRoute('gerer_vehicules');
        }

        try {
            $this->persistanceVoiturePostgresql->desactiverVehicule($idVoiture, $idUtilisateur);

            /*
             * La journalisation est faite après succès.
             * On garde ici une trace utile dans le journal d'événements
             *  de la désactivation réelle.
             */
            $this->journalEvenements->enregistrer(
                'voiture_desactivee',
                'voiture',
                $idVoiture,
                [
                    'id_utilisateur' => $idUtilisateur,
                ]
            );

            $this->addFlash('succes', 'Véhicule supprimé.');
        } catch (RuntimeException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('gerer_vehicules');
    }

    /**
     * Affiche le formulaire d'ajout d'une voiture.
     *
     * @return Response Réponse HTML rendue par Twig ou redirection.
     */
    #[Route('/espace/vehicules/ajouter', name: 'vehicule_ajouter', methods: ['GET'])]
    public function ajouter(): Response
    {
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

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

    /**
     * Traite l'ajout d'une voiture.
     *
     * La méthode lit les champs du formulaire,
     * applique les validations côté serveur,
     * puis délègue l'écriture à PostgreSQL.
     *
     * Si l'ajout réussit, l'événement significatif
     * est enregistré dans MongoDB.
     *
     * @param Request $request Requête HTTP contenant les champs du formulaire.
     *
     * @return Response|RedirectResponse Réaffichage du formulaire ou redirection.
     */
    #[Route('/espace/vehicules/ajouter', name: 'vehicule_ajouter_traitement', methods: ['POST'])]
    public function ajouterTraitement(Request $request): Response|RedirectResponse
    {
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        $jeton = (string) $request->request->get('_jeton_csrf', '');
        if (!$this->isCsrfTokenValid('ajouter_vehicule', $jeton)) {
            $this->addFlash('erreur', 'Action refusée : jeton invalide.');

            return $this->redirectToRoute('vehicule_ajouter');
        }

        /*
         * Lecture et nettoyage des champs du formulaire.
         * `trim()` retire les espaces inutiles au début et à la fin.
         */
        $immatriculation = strtoupper(trim((string) $request->request->get('immatriculation', '')));
        $date = trim((string) $request->request->get('date_1ere_mise_en_circulation', ''));
        $marque = trim((string) $request->request->get('marque', ''));
        $couleur = trim((string) $request->request->get('couleur', ''));
        $energie = trim((string) $request->request->get('energie', ''));
        $nbPlaces = (int) $request->request->get('nb_places', 0);

        $erreurs = $this->validerFormulaireVehicule($immatriculation, $date, $marque, $couleur, $energie, $nbPlaces);

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
                'voiture_ajoutee',
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

    /**
     * Affiche le formulaire de modification d'une voiture existante.
     *
     * La voiture est recherchée avec l'identifiant de la voiture
     * et celui de l'utilisateur connecté.
     * Cela évite d'afficher la voiture d'un autre compte.
     *
     * @param int $idVoiture Identifiant de la voiture.
     *
     * @return Response|RedirectResponse Réponse HTML rendue par Twig ou redirection.
     */
    #[Route(
        '/espace/vehicules/{idVoiture}/modifier',
        name: 'vehicule_modifier',
        requirements: ['idVoiture' => '\d+'],
        methods: ['GET']
    )]
    public function modifier(int $idVoiture): Response|RedirectResponse
    {
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

    /**
     * Traite la modification d'une voiture.
     *
     * La méthode relit les champs du formulaire,
     * applique les validations côté serveur,
     * vérifie que la voiture appartient bien à l'utilisateur connecté,
     * puis délègue l'écriture à PostgreSQL.
     *
     * Si la modification réussit,
     * l'événement significatif est enregistré dans MongoDB.
     *
     * @param Request $request Requête HTTP contenant les champs du formulaire.
     * @param int $idVoiture Identifiant de la voiture.
     *
     * @return Response|RedirectResponse Réaffichage du formulaire ou redirection.
     */
    #[Route(
        '/espace/vehicules/{idVoiture}/modifier',
        name: 'vehicule_modifier_traitement',
        requirements: ['idVoiture' => '\d+'],
        methods: ['POST']
    )]
    public function modifierTraitement(Request $request, int $idVoiture): Response|RedirectResponse
    {
        if (!$this->sessionUtilisateur->estConnecte()) {
            return $this->redirectToRoute('connexion');
        }

        $idUtilisateur = $this->sessionUtilisateur->idUtilisateur();
        if ($idUtilisateur === null) {
            return $this->redirectToRoute('connexion');
        }

        $jeton = (string) $request->request->get('_jeton_csrf', '');
        if (!$this->isCsrfTokenValid('modifier_vehicule_' . $idVoiture, $jeton)) {
            $this->addFlash('erreur', 'Action refusée : jeton invalide.');

            return $this->redirectToRoute('vehicule_modifier', ['idVoiture' => $idVoiture]);
        }

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
                'voiture_modifiee',
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

    /**
     * Vérifie les champs du formulaire voiture.
     *
     * Cette méthode centralise les contrôles simples du formulaire.
     * Le tableau retourné contient une entrée par champ en erreur.
     *
     * `preg_match()` vérifie une valeur
     * à l'aide d'une expression régulière,
     * c'est-à-dire un modèle de texte à respecter.
     *
     * `in_array()` vérifie que l'énergie choisie
     * appartient bien à la liste autorisée.
     *
     * @param string $immatriculation Immatriculation saisie.
     * @param string $date Date de première mise en circulation.
     * @param string $marque Marque saisie.
     * @param string $couleur Couleur saisie.
     * @param string $energie Type d'énergie choisi.
     * @param int $nbPlaces Nombre de places saisi.
     *
     * @return array<string, string> Tableau des erreurs par champ.
     */
    private function validerFormulaireVehicule(
        string $immatriculation,
        string $date,
        string $marque,
        string $couleur,
        string $energie,
        int $nbPlaces
    ): array {
        $erreurs = [];

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

    /**
     * Calcule l'ancienneté d'une voiture en années.
     *
     * La date attendue doit être au format `Y-m-d`,
     * par exemple `2021-03-10`.
     *
     * Si la date est absente, invalide
     * ou impossible à interpréter,
     * la méthode renvoie `0`.
     *
     * `DateTimeImmutable` représente une date et une heure.
     * Le mot "Immutable" signifie que l'objet n'est pas modifié directement :
     * chaque opération produit une nouvelle valeur.
     *
     * @param string $dateYmd Date au format `Y-m-d`.
     *
     * @return int Nombre d'années écoulées depuis cette date.
     */
    private function calculerAncienneteEnAnnees(string $dateYmd): int
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd);
        $erreurs = DateTimeImmutable::getLastErrors();

        $parsingKo = !$date instanceof DateTimeImmutable
            || ($erreurs !== false && ($erreurs['warning_count'] > 0 || $erreurs['error_count'] > 0));

        if ($parsingKo) {
            return 0;
        }

        $aujourdhui = new DateTimeImmutable('today');
        $diff = $date->diff($aujourdhui);

        return max(0, (int) $diff->y);
    }
}