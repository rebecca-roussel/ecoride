<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JournalEvenements;
use App\Service\PersistanceReinitialisationMotDePassePostgresql;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contrôleur du parcours "mot de passe oublié".
 *
 * Cette classe gère le formulaire dans lequel l'utilisateur saisit son e-mail
 * pour demander un lien de réinitialisation.
 *
 * Le contrôleur garde la logique HTTP :
 * affichage de la page,
 * lecture du formulaire,
 * contrôle CSRF,
 * validation simple de l'e-mail,
 * envoi du courriel
 * puis affichage d'un message neutre.
 *
 * La création du jeton en base est déléguée
 * à `PersistanceReinitialisationMotDePassePostgresql`.
 *
 * La journalisation MongoDB trace ici
 * une demande de réinitialisation réellement créée.
 */
final class MotDePasseOublieController extends AbstractController
{
    /**
     * Affiche le formulaire de demande
     * et traite son envoi.
     *
     * En GET, la méthode affiche simplement la page.
     * En POST, elle valide l'e-mail,
     * crée une demande de réinitialisation si le compte existe,
     * envoie le lien par courriel
     * puis journalise l'événement dans MongoDB.
     */
    #[Route('/mot_de_passe_oublie', name: 'mot_de_passe_oublie', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PersistanceReinitialisationMotDePassePostgresql $persistance,
        MailerInterface $serviceCourriel,
        JournalEvenements $journalEvenements,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->render('mot_de_passe_oublie/index.html.twig', [
                'message' => null,
                'erreur' => null,
                'email_saisi' => '',
            ]);
        }

        /*
         * Le jeton CSRF protège la soumission du formulaire
         * contre une requête frauduleuse.
         */
        if (!$this->isCsrfTokenValid('mot_de_passe_oublie', (string) $request->request->get('_csrf_token'))) {
            return $this->render('mot_de_passe_oublie/index.html.twig', [
                'message' => null,
                'erreur' => 'Requête invalide (CSRF).',
                'email_saisi' => '',
            ]);
        }

        $email = trim((string) $request->request->get('email', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->render('mot_de_passe_oublie/index.html.twig', [
                'message' => null,
                'erreur' => 'Adresse e-mail invalide.',
                'email_saisi' => $email,
            ]);
        }

        /*
         * La persistance renvoie soit `null` si aucun compte n'existe,
         * soit l'identifiant utilisateur avec le jeton à envoyer.
         */
        $resultat = $persistance->creerJetonPourEmail($email);

        /*
         * Le message reste volontairement neutre.
         * Cela évite de révéler si l'e-mail existe réellement dans l'application.
         */
        $messageNeutre = 'Si un compte existe avec cet e-mail, un message a été envoyé.';

        if ($resultat !== null) {
            $idUtilisateur = (int) $resultat['id_utilisateur'];
            $jeton = (string) $resultat['jeton'];

            $lien = $this->generateUrl(
                'reinitialiser_mot_de_passe',
                ['jeton' => $jeton],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $courriel = (new Email())
                ->from('ne-pas-repondre@ecoride.fr')
                ->to($email)
                ->subject('EcoRide – Réinitialisation du mot de passe')
                ->text(
                    "Bonjour,\n\n" .
                    "Ouvrez ce lien pour réinitialiser votre mot de passe :\n" .
                    $lien . "\n\n" .
                    "Ce lien expire dans 30 minutes.\n"
                );

            $serviceCourriel->send($courriel);

            $journalEvenements->enregistrer(
                'mot_de_passe_reinitialisation_demandee',
                'utilisateur',
                $idUtilisateur,
                [
                    'email' => $email,
                ]
            );
        }

        return $this->render('mot_de_passe_oublie/index.html.twig', [
            'message' => $messageNeutre,
            'erreur' => null,
            'email_saisi' => $email,
        ]);
    }
}