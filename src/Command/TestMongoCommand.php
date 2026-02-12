<?php

namespace App\Command;

use App\Service\JournalEvenements;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-mongo',
    description: 'Test d’écriture dans MongoDB (journal d’événements).'
)]
final class TestMongoCommand extends Command
{
    public function __construct(private readonly JournalEvenements $journal)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $this->journal->enregistrer('TEST_MONGODB', [
            'message' => 'Événement de test EcoRide',
            'source' => 'commande_symfony',
        ]);

        $output->writeln('OK — évènement inséré, id = ' . $id);

        return Command::SUCCESS;
    }
}
