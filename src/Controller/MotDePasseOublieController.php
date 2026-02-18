<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PersistanceReinitialisationMotDePassePostgresql;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MotDePasseOublieController extends AbstractController
{
    #[Route('/mot_de_passe_oublie', name: 'mot_de_passe_oublie', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PersistanceReinitialisationMotDePassePostgresql $persistance,
        MailerInterface $serviceCourriel,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->render('mot_de_passe_oublie/index.html.twig', [
                'message' => null,
                'erreur' => null,
                'email_saisi' => '',
            ]);
        }

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

        $jeton = $persistance->creerJetonPourEmail($email);

        // Message neutre 
        $messageNeutre = 'Si un compte existe avec cet e-mail, un message a été envoyé.';

        if ($jeton !== null) {
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
                    "Bonjour,\n\n".
                    "Ouvrez ce lien pour réinitialiser votre mot de passe :\n".
                    $lien."\n\n".
                    "Ce lien expire dans 30 minutes.\n"
                );

            $serviceCourriel->send($courriel);
        }

        return $this->render('mot_de_passe_oublie/index.html.twig', [
            'message' => $messageNeutre,
            'erreur' => null,
            'email_saisi' => $email,
        ]);
    }
}
