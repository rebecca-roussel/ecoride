<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceUtilisateurPostgresql;
use App\Service\SessionUtilisateur;
use PDOException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Contrôleur du parcours d'inscription.
 *
 * Ce contrôleur gère l'affichage du formulaire
 * et le traitement de la création du compte.
 *
 * Son rôle reste centré sur le parcours HTTP :
 * lire les champs envoyés par le formulaire,
 * vérifier les erreurs simples côté serveur,
 * appeler PostgreSQL pour créer l'utilisateur,
 * ouvrir la session
 * puis choisir la redirection.
 *
 * La photo de profil est facultative.
 * Elle est donc traitée après la création réelle du compte.
 * Si son enregistrement échoue, l'inscription reste valide
 * et l'utilisateur peut continuer sans photo.
 */
final class InscriptionController extends AbstractController
{
    /**
     * Affiche le formulaire d'inscription et traite sa soumission.
     *
     * En GET, la méthode affiche simplement la page.
     * En POST, elle contrôle les données,
     * crée le compte,
     * tente ensuite l'enregistrement éventuel de la photo,
     * journalise l'inscription dans MongoDB
     * puis ouvre la session.
     */
    #[Route('/inscription', name: 'inscription', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur,
        SessionUtilisateur $sessionUtilisateur,
        JournalEvenements $journalEvenements,
    ): Response {
        /*
         * Message bloquant affiché dans le formulaire.
         * Tant qu'il contient une valeur,
         * la création du compte ne continue pas.
         */
        $erreur = null;

        /*
         * Ces valeurs servent à réafficher le formulaire
         * si une erreur survient.
         */
        $pseudoSaisi = '';
        $emailSaisi = '';
        $roleChauffeur = false;
        $rolePassager = false;

        if ($request->isMethod('POST')) {
            /*
             * `trim()` retire les espaces inutiles
             * au début et à la fin d'une chaîne.
             */
            $pseudoSaisi = trim((string) $request->request->get('pseudo', ''));
            $emailSaisi = trim((string) $request->request->get('email', ''));
            $motDePasse = (string) $request->request->get('mot_de_passe', '');
            $confirmation = (string) $request->request->get('mot_de_passe_confirmation', '');

            /*
             * `getBoolean()` convertit la valeur du formulaire
             * en vrai booléen PHP.
             */
            $roleChauffeur = $request->request->getBoolean('role_chauffeur', false);
            $rolePassager = $request->request->getBoolean('role_passager', false);

            /*
             * Premier niveau de validation :
             * ce sont les contrôles simples
             * qui peuvent être faits sans aller en base.
             */
            if ($pseudoSaisi === '' || $emailSaisi === '' || $motDePasse === '' || $confirmation === '') {
                $erreur = 'Tous les champs sont obligatoires (sauf la photo).';
            } elseif ($motDePasse !== $confirmation) {
                $erreur = 'Les mots de passe ne correspondent pas.';
            } elseif (!$roleChauffeur && !$rolePassager) {
                $erreur = 'Choisissez au moins un rôle.';
            } elseif (mb_strlen($motDePasse) < 8) {
                $erreur = 'Le mot de passe doit faire au moins 8 caractères.';
            }

            /*
             * On vérifie ensuite les doublons les plus courants
             * avant de tenter l'insertion en base.
             */
            if ($erreur === null) {
                if ($persistanceUtilisateur->pseudoExisteDeja($pseudoSaisi)) {
                    $erreur = 'Ce pseudo est déjà utilisé.';
                } elseif ($persistanceUtilisateur->emailExisteDeja($emailSaisi)) {
                    $erreur = 'Cet email est déjà utilisé.';
                }
            }

            if ($erreur === null) {
                /*
                 * `password_hash()` transforme le mot de passe
                 * en hash sécurisé.
                 *
                 * Un hash est une version transformée du mot de passe
                 * qui peut être vérifiée plus tard,
                 * sans stocker le mot de passe en clair.
                 */
                $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

                if (!is_string($hash) || $hash === '') {
                    $erreur = "Erreur lors de la sécurisation du mot de passe.";
                } else {
                    try {
                        /*
                         * Le compte est créé sans photo dans un premier temps.
                         * Cela évite de rattacher un fichier
                         * à un utilisateur qui n'existerait pas encore.
                         */
                        $idUtilisateur = $persistanceUtilisateur->creerUtilisateur(
                            $pseudoSaisi,
                            $emailSaisi,
                            $hash,
                            $roleChauffeur,
                            $rolePassager,
                            null
                        );

                        /*
                         * La photo reste optionnelle.
                         * Si un fichier a été envoyé,
                         * on tente maintenant son enregistrement.
                         *
                         * Une erreur photo n'annule pas l'inscription.
                         * Elle produit seulement un message d'avertissement.
                         */
                        $messagePhoto = null;

                        /** @var UploadedFile|null $photo */
                        $photo = $request->files->get('photo_profil');

                        if ($photo instanceof UploadedFile) {
                            try {
                                $photoPath = $this->enregistrerPhotoProfil($photo);
                                $persistanceUtilisateur->mettreAJourPhotoProfil($idUtilisateur, $photoPath);
                            } catch (RuntimeException $e) {
                                $messagePhoto = $e->getMessage();
                            }
                        }

                        /*
                         * L'événement MongoDB est enregistré
                         * une fois que le compte existe réellement.
                         */
                        $journalEvenements->enregistrer(
                            'utilisateur_inscrit',
                            'utilisateur',
                            $idUtilisateur,
                            [
                                'pseudo' => $pseudoSaisi,
                                'role_chauffeur' => $roleChauffeur,
                                'role_passager' => $rolePassager,
                            ]
                        );

                        /*
                         * La session est ouverte juste après l'inscription.
                         */
                        $sessionUtilisateur->connecter(
                            $idUtilisateur,
                            $pseudoSaisi,
                            $roleChauffeur,
                            $rolePassager
                        );

                        if ($messagePhoto !== null) {
                            $this->addFlash(
                                'avertissement',
                                $messagePhoto . ' Vous pourrez ajouter votre photo plus tard.'
                            );
                        } else {
                            $this->addFlash('succes', 'Compte créé avec succès.');
                        }

                        return $this->redirectToRoute('accueil');
                    } catch (RuntimeException $e) {
                        /*
                         * Ici, on récupère surtout les messages métier
                         * préparés par la persistance.
                         */
                        $erreur = $e->getMessage();
                    } catch (PDOException) {
                        /*
                         * Si PostgreSQL remonte une erreur technique,
                         * on garde un message neutre côté interface.
                         */
                        $erreur = "Erreur lors de l'inscription.";
                    }
                }
            }
        }

        return $this->render('inscription/index.html.twig', [
            'erreur' => $erreur,
            'pseudo_saisi' => $pseudoSaisi,
            'email_saisi' => $emailSaisi,
            'role_chauffeur_saisi' => $roleChauffeur,
            'role_passager_saisi' => $rolePassager,
            'utilisateur_connecte' => $sessionUtilisateur->estConnecte(),
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
        ]);
    }

    /**
     * Valide puis enregistre une photo de profil sur le disque.
     *
     * Cette méthode ne crée aucun utilisateur.
     * Elle traite uniquement le fichier image :
     * contrôle du fichier,
     * création éventuelle du dossier de destination,
     * déplacement du fichier
     * puis renvoi du chemin relatif à stocker en base.
     *
     * @param UploadedFile $photo Fichier reçu depuis le formulaire.
     *
     * @return string Chemin relatif prêt à être enregistré en base.
     */
    private function enregistrerPhotoProfil(UploadedFile $photo): string
    {
        if (!$photo->isValid()) {
            throw new RuntimeException("La photo n'a pas pu être envoyée.");
        }

        /*
         * Le type MIME décrit la nature du fichier,
         * par exemple `image/jpeg` ou `image/png`.
         * Ici, on vérifie qu'on reçoit bien une image.
         */
        $typeMime = $photo->getMimeType();
        if ($typeMime === null || !str_starts_with($typeMime, 'image/')) {
            throw new RuntimeException("Le fichier choisi n'est pas une image.");
        }

        if ($photo->getSize() !== null && $photo->getSize() > 2_000_000) {
            throw new RuntimeException("L'image est trop lourde (max 2 Mo).");
        }

        /*
         * `kernel.project_dir` donne le dossier racine du projet Symfony.
         * On reconstruit ensuite le chemin du dossier public,
         * puis le sous-dossier `photos`.
         */
        $dossierPublic = (string) $this->getParameter('kernel.project_dir') . '/public';
        $dossierPhotos = $dossierPublic . '/photos';

        if (!is_dir($dossierPhotos)) {
            @mkdir($dossierPhotos, 0o775, true);
        }

        if (!is_dir($dossierPhotos)) {
            throw new RuntimeException("Impossible de créer le dossier des photos.");
        }

        /*
         * Le nom du fichier est généré aléatoirement
         * pour éviter les collisions entre deux images.
         */
        $extension = $photo->guessExtension() ?: 'jpg';
        $nomFichier = 'profil_' . bin2hex(random_bytes(8)) . '.' . $extension;

        try {
            /*
             * `move()` déplace le fichier reçu
             * dans le dossier choisi sur le serveur.
             */
            $photo->move($dossierPhotos, $nomFichier);
        } catch (Throwable) {
            throw new RuntimeException("La photo n'a pas pu être enregistrée.");
        }

        $cheminFinal = $dossierPhotos . '/' . $nomFichier;
        if (!is_file($cheminFinal)) {
            throw new RuntimeException("La photo n'a pas été enregistrée sur le serveur.");
        }

        /*
         * Le chemin enregistré en base reste un chemin relatif.
         * Cela facilite ensuite sa réutilisation dans l'application.
         */
        return 'photos/' . $nomFichier;
    }
}