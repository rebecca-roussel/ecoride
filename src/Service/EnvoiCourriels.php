<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Service d'envoi des courriels automatiques EcoRide.
 *
 * Cette classe centralise la préparation et l'envoi
 * des courriels liés au parcours covoiturage.
 *
 * Son rôle consiste à :
 * - construire les messages avec les bons destinataires ;
 * - utiliser les templates Twig prévus pour chaque cas ;
 * - injecter les données utiles dans le contexte du courriel ;
 * - déléguer l'envoi réel au composant Mailer de Symfony.
 *
 * La classe ne décide pas elle-même
 * qui doit recevoir un message ni à quel moment.
 * Ces choix sont faits ailleurs dans l'application.
 * Elle reçoit simplement les données nécessaires,
 * puis prépare les courriels correspondants.
 */
final class EnvoiCourriels
{
    /**
     * Initialise le service d'envoi de courriels.
     *
     * @param MailerInterface $expediteur Service Symfony chargé d'envoyer les messages.
     * @param string $courrielExpediteur Adresse email utilisée comme expéditeur.
     */
    public function __construct(
        private MailerInterface $expediteur,
        private string $courrielExpediteur
    ) {
    }

    /**
     * Envoie un courriel d'annulation à chaque participant d'un covoiturage.
     *
     * Cette méthode parcourt la liste des participants,
     * construit un courriel individuel pour chacun,
     * puis déclenche l'envoi.
     *
     * Le contenu du message est généré à partir
     * du template Twig "courriels/annulation_covoiturage.html.twig".
     *
     * Le contexte transmis au template contient :
     * - le pseudo du participant ;
     * - les données du covoiturage annulé.
     *
     * @param array $participants Liste des participants à prévenir.
     * @param array $covoiturage Données utiles sur le covoiturage annulé.
     */
    public function envoyerAnnulationCovoiturage(array $participants, array $covoiturage): void
    {
        /*
         * On traite les participants un par un
         * afin que chaque personne reçoive
         * un courriel personnalisé.
         */
        foreach ($participants as $p) {
            /*
             * On construit un TemplatedEmail.
             *
             * TemplatedEmail permet de générer le contenu
             * du message à partir d'un template Twig,
             * avec des données injectées dans un contexte.
             */
            $courriel = (new TemplatedEmail())
                /*
                 * Adresse expéditrice du message.
                 *
                 * Address permet d'associer une adresse email
                 * à un nom lisible affiché dans le courriel.
                 */
                ->from(new Address($this->courrielExpediteur, 'EcoRide'))

                /*
                 * Adresse du destinataire courant.
                 */
                ->to($p['email'])

                /*
                 * Sujet affiché dans la boîte mail du destinataire.
                 */
                ->subject('EcoRide — Covoiturage annulé')

                /*
                 * Template Twig utilisé pour le contenu HTML du message.
                 */
                ->htmlTemplate('courriels/annulation_covoiturage.html.twig')

                /*
                 * Données transmises au template Twig.
                 *
                 * Le template pourra utiliser :
                 * - le pseudo du participant ;
                 * - les informations du covoiturage.
                 */
                ->context([
                    'pseudo' => $p['pseudo'],
                    'covoiturage' => $covoiturage,
                ]);

            /*
             * Envoi réel du courriel
             * via le service Mailer de Symfony.
             */
            $this->expediteur->send($courriel);
        }
    }

    /**
     * Envoie un courriel de demande de validation de trajet
     * à chaque participant concerné.
     *
     * Cette méthode suit la même logique
     * que l'annulation de covoiturage :
     * elle parcourt les participants,
     * construit un courriel individuel,
     * puis déclenche l'envoi.
     *
     * Le contenu du message est généré à partir
     * du template Twig "courriels/demande_validation_trajet.html.twig".
     *
     * Le contexte transmis au template contient :
     * - le pseudo du participant ;
     * - les données du covoiturage à valider.
     *
     * @param array $participants Liste des participants à prévenir.
     * @param array $covoiturage Données utiles sur le covoiturage concerné.
     */
    public function envoyerDemandeValidationTrajet(array $participants, array $covoiturage): void
    {
        /*
         * Chaque participant reçoit son propre message.
         */
        foreach ($participants as $p) {
            /*
             * Construction du courriel à partir d'un template Twig.
             */
            $courriel = (new TemplatedEmail())
                /*
                 * Adresse expéditrice et nom affiché.
                 */
                ->from(new Address($this->courrielExpediteur, 'EcoRide'))

                /*
                 * Adresse du destinataire courant.
                 */
                ->to($p['email'])

                /*
                 * Sujet du message.
                 */
                ->subject('EcoRide — Merci de valider votre trajet')

                /*
                 * Template HTML utilisé pour ce type de courriel.
                 */
                ->htmlTemplate('courriels/demande_validation_trajet.html.twig')

                /*
                 * Données injectées dans le template.
                 */
                ->context([
                    'pseudo' => $p['pseudo'],
                    'covoiturage' => $covoiturage,
                ]);

            /*
             * Envoi du message via Mailer.
             */
            $this->expediteur->send($courriel);
        }
    }
}