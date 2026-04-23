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

/**
 * Contrôleur de la page profil.
 *
 * Cette classe gère l'affichage du profil utilisateur
 * et la mise à jour de la photo de profil.
 *
 * Flux pris en charge :
 * - en GET, la méthode affiche les données du profil ;
 * - en POST avec l'action "photo", la méthode traite l'envoi d'une image.
 *
 * Répartition des responsabilités :
 * - le contrôleur gère la requête HTTP, les vérifications simples
 *   et les redirections ;
 * - le service SessionUtilisateur donne l'identité de l'utilisateur connecté ;
 * - le service PersistanceUtilisateurPostgresql lit et met à jour
 *   les données du profil dans PostgreSQL.
 *
 * Règle d'accès :
 * cette page est réservée à un utilisateur connecté.
 */
final class ProfilController extends AbstractController
{
    /**
     * Affiche le profil utilisateur et traite l'envoi de la photo.
     *
     * Route :
     * - GET  /profil : affichage des données du profil ;
     * - POST /profil : traitement du formulaire d'envoi de photo
     *   si le champ "action" vaut "photo".
     *
     * Déroulement général :
     * la méthode vérifie d'abord qu'un utilisateur est connecté,
     * charge les données du profil, traite l'upload de photo
     * si la requête est un POST, prépare les données lisibles
     * par la vue Twig, puis renvoie la page profil.
     *
     * Le traitement de la photo applique plusieurs garde-fous :
     * - présence réelle d'un fichier ;
     * - upload valide ;
     * - type MIME de type image ;
     * - taille maximale limitée à 2 Mo ;
     * - génération d'un nom de fichier unique ;
     * - enregistrement du chemin relatif en base ;
     * - suppression de l'ancienne photo seulement si elle se trouve
     *   bien dans le dossier prévu.
     *
     * Le flux POST se termine par une redirection vers GET.
     * Ce schéma est appelé Post / Redirect / Get.
     * Il évite qu'un rafraîchissement du navigateur
     * renvoie le formulaire une seconde fois.
     *
     * @param Request $request Requête HTTP courante.
     * @param SessionUtilisateur $sessionUtilisateur Service de lecture de la session utilisateur.
     * @param PersistanceUtilisateurPostgresql $persistanceUtilisateur
     *        Service de lecture et d'écriture du profil dans PostgreSQL.
     *
     * @return Response Réponse HTML de la page profil ou redirection.
     */
    #[Route('/profil', name: 'profil', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SessionUtilisateur $sessionUtilisateur,
        PersistanceUtilisateurPostgresql $persistanceUtilisateur
    ): Response {
        /*
         * Contrôle d'accès
         *
         * La page profil est réservée à un utilisateur connecté.
         * On récupère l'identifiant stocké en session.
         * Si aucun identifiant n'est présent,
         * on redirige vers la page de connexion.
         */
        $idUtilisateur = $sessionUtilisateur->idUtilisateur();
        if (null === $idUtilisateur) {
            return $this->redirectToRoute('connexion');
        }

        /*
         * Lecture des données du profil
         *
         * On charge les données du profil une seule fois au début
         * du traitement pour éviter plusieurs lectures inutiles.
         *
         * Cas particulier :
         * une session peut exister alors que le compte n'est plus trouvable
         * en base, par exemple après une suppression ou une incohérence.
         * Dans ce cas, on ferme la session puis on renvoie vers la connexion.
         */
        $utilisateur = $persistanceUtilisateur->obtenirDonneesProfil($idUtilisateur);
        if (null === $utilisateur) {
            $sessionUtilisateur->deconnecter();

            return $this->redirectToRoute('connexion');
        }

        /*
         * Traitement du formulaire d'upload de photo
         *
         * Ce bloc ne s'exécute que si :
         * - la requête est un POST ;
         * - le champ "action" indique qu'il s'agit bien
         *   du formulaire de photo.
         *
         * Cela permet de distinguer ce traitement
         * d'autres actions possibles sur la page profil.
         */
        if ($request->isMethod('POST') && $request->request->get('action') === 'photo') {
            /** @var UploadedFile|null $photo */
            $photo = $request->files->get('photo_profil');

            /*
             * Premier garde-fou :
             * si aucun fichier exploitable n'est reçu,
             * on quitte le traitement et on revient sur la page.
             */
            if (!$photo instanceof UploadedFile) {
                return $this->redirectToRoute('profil');
            }

            /*
             * Deuxième garde-fou :
             * si Symfony signale un échec d'upload,
             * on ne poursuit pas le traitement.
             */
            if (!$photo->isValid()) {
                return $this->redirectToRoute('profil');
            }

            /*
             * Contrôle du type MIME
             *
             * Le type MIME décrit la nature du fichier.
             * Un début en "image/" correspond par exemple à image/jpeg,
             * image/png ou image/webp.
             */
            $typeMime = $photo->getMimeType();
            if (null === $typeMime || !str_starts_with($typeMime, 'image/')) {
                return $this->redirectToRoute('profil');
            }

            /*
             * Contrôle de la taille
             *
             * La limite retenue ici est de 2 Mo.
             * Cela réduit les risques de fichiers trop volumineux
             * dans l'espace public du projet.
             */
            if (null !== $photo->getSize() && $photo->getSize() > 2_000_000) {
                return $this->redirectToRoute('profil');
            }

            /*
             * On mémorise l'ancien chemin de photo avant modification.
             * Cette valeur servira après la mise à jour pour supprimer
             * le fichier précédent si nécessaire.
             */
            $anciennePhotoPath = isset($utilisateur['photo_path'])
                ? (string) $utilisateur['photo_path']
                : null;

            /*
             * Préparation des dossiers de destination
             *
             * - kernel.project_dir donne la racine du projet Symfony ;
             * - /public est le dossier accessible depuis le navigateur ;
             * - /public/photos contient les photos de profil uploadées.
             */
            $dossierPublic = $this->getParameter('kernel.project_dir') . '/public';
            $dossierPhotos = $dossierPublic . '/photos';

            /*
             * On crée le dossier /public/photos s'il n'existe pas encore.
             *
             * Les permissions 775 permettent un usage classique :
             * lecture et écriture pour le propriétaire et le groupe,
             * lecture et exécution pour les autres.
             */
            if (!is_dir($dossierPhotos)) {
                @mkdir($dossierPhotos, 0o775, true);
            }

            /*
             * Génération d'un nom de fichier unique
             *
             * - guessExtension() essaye de retrouver une extension logique ;
             * - si aucune extension n'est trouvée, on retient jpg par défaut ;
             * - random_bytes() produit une chaîne aléatoire
             *   transformée ensuite en hexadécimal avec bin2hex().
             */
            $extension = $photo->guessExtension() ?: 'jpg';
            $nomFichier = 'profil_' . bin2hex(random_bytes(8)) . '.' . $extension;

            /*
             * Déplacement du fichier uploadé
             *
             * Cette opération transfère le fichier reçu
             * vers son emplacement final dans le projet.
             *
             * En cas d'échec au moment du déplacement,
             * on revient simplement sur la page profil.
             */
            try {
                $photo->move($dossierPhotos, $nomFichier);
            } catch (\Throwable) {
                return $this->redirectToRoute('profil');
            }

            /*
             * On prépare ensuite le chemin relatif stocké en base.
             *
             * On enregistre "photos/nom_du_fichier" plutôt qu'un chemin absolu
             * pour garder une donnée exploitable dans l'application
             * sans dépendre du chemin système complet.
             */
            $nouvellePhotoPath = 'photos/' . $nomFichier;

            /*
             * Mise à jour de la base PostgreSQL :
             * le nouveau chemin relatif devient la photo du profil utilisateur.
             */
            $persistanceUtilisateur->mettreAJourPhotoProfil($idUtilisateur, $nouvellePhotoPath);

            /*
             * Une fois la base mise à jour,
             * on supprime l'ancienne photo si elle appartient bien
             * au dossier géré par l'application.
             *
             * Cette suppression est déléguée à une méthode privée
             * pour garder le flux principal plus lisible.
             */
            $this->supprimerAnciennePhotoSiNecessaire($dossierPublic, $anciennePhotoPath);

            /*
             * Schéma Post / Redirect / Get
             *
             * Après un POST réussi, on redirige vers GET /profil.
             * Ce mécanisme évite qu'un rafraîchissement du navigateur
             * relance l'envoi du formulaire.
             */
            return $this->redirectToRoute('profil');
        }

        /*
         * Préparation des données pour l'affichage
         *
         * Le statut stocké en base est transformé ici
         * en libellé plus lisible pour l'interface.
         */
        $statutBrut = (string) ($utilisateur['statut'] ?? 'ACTIF');
        $statutLisible = $statutBrut === 'SUSPENDU' ? 'Suspendu' : 'Actif';

        /*
         * On prépare un tableau simple destiné à Twig.
         *
         * - pseudo : pseudo affiché sur la page ;
         * - email : adresse email du compte ;
         * - statut : libellé lisible ;
         * - photo : URL finale exploitable par la vue.
         *
         * urlPhotoProfil() délègue au service la construction
         * de l'URL adaptée à l'affichage.
         */
        $donnees = [
            'pseudo' => (string) ($utilisateur['pseudo'] ?? 'EcoRider'),
            'email' => (string) ($utilisateur['email'] ?? ''),
            'statut' => $statutLisible,
            'photo' => $persistanceUtilisateur->urlPhotoProfil($utilisateur['photo_path'] ?? null),
        ];

        /*
         * Rendu de la vue profil
         *
         * Le contrôleur transmet les données préparées au template Twig
         * qui se charge ensuite de l'affichage HTML.
         */
        return $this->render('profil/index.html.twig', [
            'donnees' => $donnees,
        ]);
    }

    /**
     * Supprime l'ancienne photo de profil si elle est réellement supprimable.
     *
     * Cette méthode applique plusieurs garde-fous avant toute suppression :
     * - l'ancien chemin doit exister ;
     * - il doit contenir une valeur non vide ;
     * - il doit commencer par "photos/" ;
     * - le chemin final doit pointer vers un vrai fichier.
     *
     * Le but est d'éviter une suppression hors du dossier prévu
     * ou une tentative sur une valeur incohérente.
     *
     * @param string $dossierPublic Chemin absolu du dossier public du projet.
     * @param string|null $anciennePhotoPath Chemin relatif de l'ancienne photo en base.
     */
    private function supprimerAnciennePhotoSiNecessaire(string $dossierPublic, ?string $anciennePhotoPath): void
    {
        /*
         * Si aucun ancien chemin n'existe,
         * il n'y a rien à supprimer.
         */
        if (null === $anciennePhotoPath) {
            return;
        }

        /*
         * On nettoie la chaîne pour éviter un chemin vide
         * composé seulement d'espaces.
         */
        $anciennePhotoPathNettoyee = trim($anciennePhotoPath);
        if ($anciennePhotoPathNettoyee === '') {
            return;
        }

        /*
         * On limite la suppression au dossier "photos/".
         *
         * Cette garde évite de supprimer un fichier
         * situé ailleurs dans le dossier public.
         */
        if (!str_starts_with($anciennePhotoPathNettoyee, 'photos/')) {
            return;
        }

        /*
         * On reconstruit le chemin absolu du fichier à partir
         * du dossier public et du chemin relatif stocké en base.
         */
        $cheminFichier = $dossierPublic . '/' . $anciennePhotoPathNettoyee;

        /*
         * On supprime seulement si le chemin désigne
         * réellement un fichier.
         *
         * is_file() évite de lancer unlink()
         * sur un chemin inexistant ou non adapté.
         */
        if (is_file($cheminFichier)) {
            @unlink($cheminFichier);
        }
    }
}