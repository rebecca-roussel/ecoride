<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ConnexionPostgresql;
use App\Service\SessionUtilisateur;
use PDOException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InscriptionController extends AbstractController
{
    #[Route('/inscription', name: 'inscription', methods: ['GET','POST'])]
    public function index(
        Request $request,
        ConnexionPostgresql $connexion,
        SessionUtilisateur $sessionUtilisateur
    ): Response {
        $erreur = null;

        // Pour ré-afficher ce que l'utilisateur a tapé en cas d'erreur
        $pseudoSaisi = '';
        $emailSaisi = '';
        $roleChauffeur = false;
        $rolePassager = false;

        if ($request->isMethod('POST')) {
            $pseudoSaisi = trim((string) $request->request->get('pseudo'));
            $emailSaisi = trim((string) $request->request->get('email'));
            $motDePasse = (string) $request->request->get('mot_de_passe');
            $confirmation = (string) $request->request->get('mot_de_passe_confirmation');

            $roleChauffeur = $request->request->getBoolean('role_chauffeur', false);
            $rolePassager  = $request->request->getBoolean('role_passager', false);

            if ($pseudoSaisi === '' || $emailSaisi === '' || $motDePasse === '' || $confirmation === '') {
                $erreur = "Tous les champs sont obligatoires (sauf la photo).";
            } elseif ($motDePasse !== $confirmation) {
                $erreur = "Les mots de passe ne correspondent pas.";
            } elseif (!$roleChauffeur && !$rolePassager) {
                $erreur = "Choisissez au moins un rôle.";
            } elseif (mb_strlen($motDePasse) < 8) {
                $erreur = "Le mot de passe doit faire au moins 8 caractères.";
            } else {
                // Photo (optionnel)
                $photoPath = null;
                /** @var UploadedFile|null $photo */
                $photo = $request->files->get('photo_profil');

                if ($photo instanceof UploadedFile) {
                    if (!$photo->isValid()) {
                        $erreur = "La photo n'a pas pu être envoyée.";
                    } else {
                        $typeMime = (string) $photo->getMimeType();
                        if (!str_starts_with($typeMime, 'image/')) {
                            $erreur = "Le fichier choisi n'est pas une image.";
                        } elseif ($photo->getSize() !== null && $photo->getSize() > 2_000_000) {
                            $erreur = "L'image est trop lourde (max 2 Mo).";
                        } else {
                            // Dossier cible : public/photos : photos/muriel.jpg)
                            $dossierPublic = $this->getParameter('kernel.project_dir') . '/public';
                            $dossierPhotos = $dossierPublic . '/photos';

                            if (!is_dir($dossierPhotos)) {
                                @mkdir($dossierPhotos, 0775, true);
                            }

                            $extension = $photo->guessExtension() ?: 'jpg';
                            $nomFichier = 'profil_' . bin2hex(random_bytes(8)) . '.' . $extension;

                            $photo->move($dossierPhotos, $nomFichier);

                            // Chemin stocké en BDD 
                            $photoPath = 'photos/' . $nomFichier;
                        }
                    }
                }

                if ($erreur === null) {
                    $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

                    try {
                        $pdo = $connexion->obtenirPdo();
                        $stmt = $pdo->prepare('
                            INSERT INTO utilisateur (
                                pseudo, email, mot_de_passe_hash,
                                credits, role_chauffeur, role_passager,
                                photo_path, statut
                            )
                            VALUES (
                                :pseudo, :email, :hash,
                                20, :role_chauffeur, :role_passager,
                                :photo_path, \'ACTIF\'
                            )
                            RETURNING id_utilisateur
                        ');

                        $stmt->execute([
                            'pseudo' => $pseudoSaisi,
                            'email' => $emailSaisi,
                            'hash' => $hash,
                            'role_chauffeur' => $roleChauffeur,
                            'role_passager' => $rolePassager,
                            'photo_path' => $photoPath, // null si pas de photo
                        ]);

                        $idUtilisateur = (int) $stmt->fetchColumn();
                        $sessionUtilisateur->connecter($idUtilisateur, $pseudoSaisi);

                        return $this->redirectToRoute('accueil');
                    } catch (PDOException $e) {
                        $message = $e->getMessage();

                        // Noms courants générés par PostgreSQL 
                        if (str_contains($message, 'utilisateur_pseudo_key')) {
                            $erreur = "Ce pseudo est déjà utilisé.";
                        } elseif (str_contains($message, 'utilisateur_email_key')) {
                            $erreur = "Cet email est déjà utilisé.";
                        } else {
                            $erreur = "Erreur lors de l'inscription.";
                        }
                    }
                }
            }
        }

        return $this->render('inscription/index.html.twig', [
            'erreur' => $erreur,

            // valeurs à ré-afficher
            'pseudo_saisi' => $pseudoSaisi,
            'email_saisi' => $emailSaisi,
            'role_chauffeur_saisi' => $roleChauffeur,
            'role_passager_saisi' => $rolePassager,

            // ton système de session “maison”
            'utilisateur_connecte' => $sessionUtilisateur->estConnecte(),
            'utilisateur_pseudo' => $sessionUtilisateur->pseudo(),
        ]);
    }
}
