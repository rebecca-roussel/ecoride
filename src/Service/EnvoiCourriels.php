<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class EnvoiCourriels
{
    public function __construct(
        private MailerInterface $expediteur,
        private string $courrielExpediteur
    ) {}

    public function envoyerAnnulationCovoiturage(array $participants, array $covoiturage): void
    {
        foreach ($participants as $p) {
            $courriel = (new TemplatedEmail())
                ->from(new Address($this->courrielExpediteur, 'EcoRide'))
                ->to($p['email'])
                ->subject('EcoRide — Covoiturage annulé')
                ->htmlTemplate('courriels/annulation_covoiturage.html.twig')
                ->context([
                    'pseudo' => $p['pseudo'],
                    'covoiturage' => $covoiturage,
                ]);

            $this->expediteur->send($courriel);
        }
    }

    public function envoyerDemandeValidationTrajet(array $participants, array $covoiturage): void
    {
        foreach ($participants as $p) {
            $courriel = (new TemplatedEmail())
                ->from(new Address($this->courrielExpediteur, 'EcoRide'))
                ->to($p['email'])
                ->subject('EcoRide — Merci de valider votre trajet')
                ->htmlTemplate('courriels/demande_validation_trajet.html.twig')
                ->context([
                    'pseudo' => $p['pseudo'],
                    'covoiturage' => $covoiturage,
                ]);

            $this->expediteur->send($courriel);
        }
    }
}