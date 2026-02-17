<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class TestMailController extends AbstractController
{
    #[Route('/test-mail', name: 'test_mail', methods: ['GET'])]
    public function envoyer(MailerInterface $mailer): Response
    {
        $email = (new Email())
            ->from('no-reply@ecoride.fr')
            ->to('test@ecoride.fr')
            ->subject('Test EcoRide')
            ->text('Mail de test via Symfony Mailer.');

        $mailer->send($email);

        return new Response('Mail envoyé ✅');
    }
}
