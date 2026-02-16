<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceUtilisateurPostgresql;
use App\Service\SessionUtilisateur;
use PDOException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class InscriptionController extends AbstractController
{
    #[Route('/inscription', name: 'inscription', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur,
        SessionUtilisateur $sessionUtilisateur,
    ): Response {
        $erreur = null;

        /* Valeurs à ré-afficher si je retombe sur le formulaire */
        $pseudoSaisi = '';
        $emailSaisi = '';
        $roleChauffeur = false;
        $rolePassager = false;

        if ($request->isMethod('POST')) {
            $pseudoSaisi = trim((string) $request->request->get('pseudo', ''));
            $emailSaisi = trim((string) $request->request->get('email', ''));
            $motDePasse = (string) $request->request->get('mot_de_passe', '');
            $confirmation = (string) $request->request->get('mot_de_passe_confirmation', '');

            $roleChauffeur = $request->request->getBoolean('role_chauffeur', false);
            $rolePassager = $request->request->getBoolean('role_passager', false);

            if ('' === $pseudoSaisi || '' === $emailSaisi || '' === $motDePasse || '' === $confirmation) {
                $erreur = 'Tous les champs sont obligatoires (sauf la photo).';
            } elseif ($motDePasse !== $confirmation) {
                $erreur = 'Les mots de passe ne correspondent pas.';
            } elseif (!$roleChauffeur && !$rolePassager) {
                $erreur = 'Choisissez au moins un rôle.';
            } elseif (mb_strlen($motDePasse) < 8) {
                $erreur = 'Le mot de passe doit faire au moins 8 caractères.';
            } else {
                /* Photo optionnelle */
                $photoPath = null;

                /** @var UploadedFile|null $photo */
                $photo = $request->files->get('photo_profil');

                if ($photo instanceof UploadedFile) {
                    if (!$photo->isValid()) {
                        $erreur = "La photo n'a pas pu être envoyée.";
                    } else {
                        $typeMime = $photo->getMimeType();

                        if ($typeMime === null || !str_starts_with($typeMime, 'image/')) {
                            $erreur = "Le fichier choisi n'est pas une image.";
                        } elseif ($photo->getSize() !== null && $photo->getSize() > 2_000_000) {
                            $erreur = "L'image est trop lourde (max 2 Mo).";
                        } else {
                            $dossierPublic = (string) $this->getParameter('kernel.project_dir') . '/public';
                            $dossierPhotos = $dossierPublic . '/photos';

                            if (!is_dir($dossierPhotos)) {
                                // On crée le dossier si absent (en ignorant l’erreur si un autre process le crée juste avant)
                                @mkdir($dossierPhotos, 0o775, true);
                            }

                            if (!is_dir($dossierPhotos)) {
                                $erreur = "Impossible de créer le dossier des photos.";
                            } else {
                                $extension = $photo->guessExtension() ?: 'jpg';
                                $nomFichier = 'profil_' . bin2hex(random_bytes(8)) . '.' . $extension;

                                try {
                                    $photo->move($dossierPhotos, $nomFichier);

                                    $cheminFinal = $dossierPhotos . '/' . $nomFichier;
                                    if (!is_file($cheminFinal)) {
                                        $erreur = "La photo n'a pas été enregistrée sur le serveur.";
                                    } else {
                                        /* Chemin relatif enregistré en base */
                                        $photoPath = 'photos/' . $nomFichier;
                                    }
                                } catch (Throwable $e) {
                                    $erreur = "La photo n'a pas pu être enregistrée.";
                                }
                            }
                        }
                    }
                }

                if (null === $erreur) {
                    /* Vérifs simples avant insertion */
                    if ($persistanceUtilisateur->pseudoExisteDeja($pseudoSaisi)) {
                        $erreur = 'Ce pseudo est déjà utilisé.';
                    } elseif ($persistanceUtilisateur->emailExisteDeja($emailSaisi)) {
                        $erreur = 'Cet email est déjà utilisé.';
                    }
                }

                if (null === $erreur) {
                    $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

                    if (!is_string($hash) || $hash === '') {
                        $erreur = "Erreur lors de la sécurisation du mot de passe.";
                    } else {
                        try {
                            $idUtilisateur = $persistanceUtilisateur->creerUtilisateur(
                                $pseudoSaisi,
                                $emailSaisi,
                                $hash,
                                $roleChauffeur,
                                $rolePassager,
                                $photoPath
                            );

                            // ✅ IMPORTANT : connecter attend 4 paramètres maintenant
                            $sessionUtilisateur->connecter($idUtilisateur, $pseudoSaisi, $roleChauffeur, $rolePassager);

                            return $this->redirectToRoute('accueil');
                        } catch (PDOException $e) {
                            $message = $e->getMessage();

                            if (str_contains($message, 'utilisateur_pseudo_key')) {
                                $erreur = 'Ce pseudo est déjà utilisé.';
                            } elseif (str_contains($message, 'utilisateur_email_key')) {
                                $erreur = 'Cet email est déjà utilisé.';
                            } else {
                                $erreur = "Erreur lors de l'inscription.";
                            }
                        }
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
}

