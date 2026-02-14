<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceUtilisateurPostgresql;
use App\Service\SessionUtilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'profil', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur
    ): Response {
        /*
          PLAN (ProfilController) :

          1) Sécurité
             - si je ne suis pas connectée, je renvoie vers /connexion

          2) Lecture de l’utilisateur
             - je récupère les données du profil depuis PostgreSQL
             - ça sert à afficher la page
             - et aussi à connaître l’ancienne photo (pour la supprimer si je change)

          3) Si je poste une nouvelle photo (POST + action photo)
             - je vérifie que le fichier est bien une image et pas trop lourd
             - je l’enregistre dans /public/photos
             - j’enregistre le chemin en base (photo_path)
             - je supprime l’ancienne photo si elle était dans /photos

          4) Affichage
             - je prépare les données pour Twig (statut lisible + url photo)
        */

        // 1) Sécurité
        $idUtilisateur = $sessionUtilisateur->idUtilisateur();
        if (null === $idUtilisateur) {
            return $this->redirectToRoute('connexion');
        }

        // 2) Je charge l'utilisateur une seule fois
        //    Comme ça, je peux à la fois afficher la page ET récupérer l'ancienne photo avant de la remplacer.
        $utilisateur = $persistanceUtilisateur->obtenirDonneesProfil($idUtilisateur);
        if (null === $utilisateur) {
            // Cas rare, session incohérente (ex utilisateur supprimé)
            $sessionUtilisateur->deconnecter();
            return $this->redirectToRoute('connexion');
        }

        // 3) Upload photo (si POST action photo)
        if ($request->isMethod('POST') && $request->request->get('action') === 'photo') {
            /** @var UploadedFile|null $photo */
            $photo = $request->files->get('photo_profil');

            // Sécurité : si je n'ai pas de fichier, je reviens sur la page
            if (!$photo instanceof UploadedFile) {
                return $this->redirectToRoute('profil');
            }

            // Sécurité : si l'upload a échoué, je reviens sur la page
            if (!$photo->isValid()) {
                return $this->redirectToRoute('profil');
            }

            // Sécurité : je vérifie que c'est une image
            $typeMime = $photo->getMimeType();
            if (null === $typeMime || !str_starts_with($typeMime, 'image/')) {
                return $this->redirectToRoute('profil');
            }

            // Sécurité : je limite la taille à 2 Mo
            if (null !== $photo->getSize() && $photo->getSize() > 2_000_000) {
                return $this->redirectToRoute('profil');
            }

            // Je garde l'ancienne photo pour pouvoir la supprimer après
            $anciennePhotoPath = isset($utilisateur['photo_path'])
                ? (string) $utilisateur['photo_path']
                : null;

            // Dossiers de destination
            $dossierPublic = $this->getParameter('kernel.project_dir') . '/public';
            $dossierPhotos = $dossierPublic . '/photos';

            // Je crée le dossier si besoin
            if (!is_dir($dossierPhotos)) {
                @mkdir($dossierPhotos, 0o775, true);
            }

            // Je génère un nom de fichier unique
            $extension = $photo->guessExtension() ?: 'jpg';
            $nomFichier = 'profil_' . bin2hex(random_bytes(8)) . '.' . $extension;

            // Je déplace le fichier uploadé dans /public/photos
            try {
                $photo->move($dossierPhotos, $nomFichier);
            } catch (\Throwable) {
                // Si ça plante, je reste simple : je reviens sur la page
                return $this->redirectToRoute('profil');
            }

            // Je stocke en base un chemin relatif 
            $nouvellePhotoPath = 'photos/' . $nomFichier;

            // Mise à jour BDD
            $persistanceUtilisateur->mettreAJourPhotoProfil($idUtilisateur, $nouvellePhotoPath);

            // Nettoyage : si l'ancienne photo était un vrai fichier dans photos, je la supprime
            $this->supprimerAnciennePhotoSiNecessaire($dossierPublic, $anciennePhotoPath);

            // POST → Redirect → GET (évite le renvoi au refresh)
            return $this->redirectToRoute('profil');
        }

        // 4) Préparation des données pour l'affichage
        $statutBrut = (string) ($utilisateur['statut'] ?? 'ACTIF');
        $statutLisible = $statutBrut === 'SUSPENDU' ? 'Suspendu' : 'Actif';

        $donnees = [
            'pseudo' => (string) ($utilisateur['pseudo'] ?? 'EcoRider'),
            'email' => (string) ($utilisateur['email'] ?? ''),
            'statut' => $statutLisible,
            'photo' => $persistanceUtilisateur->urlPhotoProfil($utilisateur['photo_path'] ?? null),
        ];

        return $this->render('profil/index.html.twig', [
            'donnees' => $donnees,
        ]);
    }

    /*
      Supprime l'ancienne photo si elle correspond à un fichier dans public/photos.
      Je fais exprès de limiter à "photos/..." pour ne pas supprimer une icône ou autre ressource.
    */
    private function supprimerAnciennePhotoSiNecessaire(string $dossierPublic, ?string $anciennePhotoPath): void
    {
        if (null === $anciennePhotoPath) {
            return;
        }

        $anciennePhotoPathNettoyee = trim($anciennePhotoPath);
        if ($anciennePhotoPathNettoyee === '') {
            return;
        }

        // Je ne supprime que les photos qui sont réellement dans le dossier photos
        if (!str_starts_with($anciennePhotoPathNettoyee, 'photos/')) {
            return;
        }

        $cheminFichier = $dossierPublic . '/' . $anciennePhotoPathNettoyee;

        // Pour la sécurité je supprime uniquement si c'est bien un fichier
        if (is_file($cheminFichier)) {
            @unlink($cheminFichier);
        }
    }
}
