<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SampleDataGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-sample-data',
    description: 'Seeds sample data for the stock reservation demo',
)]
final class SeedSampleDataCommand extends Command
{
    public function __construct(
        private readonly SampleDataGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Delete all existing data before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reset = (bool) $input->getOption('reset');

        $summary = $this->generator->seed($reset);

        $io->success('Sample data seeded successfully.');

        $io->definitionList(
            ['Products'         => $summary->products],
            ['Warehouses'       => $summary->warehouses],
            ['Warehouse stocks' => $summary->warehouseStocks],
            ['Orders'           => $summary->orders],
            ['Order items'      => $summary->orderItems],
            ['Reset'            => $summary->reset ? 'yes' : 'no'],
        );

        return Command::SUCCESS;
    }
}
