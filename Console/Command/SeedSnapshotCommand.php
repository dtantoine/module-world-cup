<?php
/**
 * Antoine World Cup Hub — dev-only: seed a deterministic snapshot into cache so
 * E2E does not depend on the live upstream. Guarded to non-production modes.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Console\Command;

use Antoine\WorldCup\Model\Snapshot\Builder;
use Antoine\WorldCup\Model\Snapshot\Repository;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedSnapshotCommand extends Command
{
    /**
     * @param State $appState
     * @param Builder $builder
     * @param Repository $repository
     * @param string|null $name
     */
    public function __construct(
        private readonly State $appState,
        private readonly Builder $builder,
        private readonly Repository $repository,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure the command name and description.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('antoine:worldcup:seed-snapshot')
            ->setDescription('Seed a deterministic World Cup snapshot (dev/E2E only).');
    }

    /**
     * Seed the deterministic snapshot into cache and write confirmation output.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->appState->getMode() === State::MODE_PRODUCTION) {
            $output->writeln('<error>Refusing to seed in production mode.</error>');
            return Command::FAILURE;
        }
        // One live, one finished, rest upcoming — derived from bundled fixtures.
        $games = [
            ['id' => '1', 'home_score' => '2', 'away_score' => '1', 'finished' => 'FALSE', 'time_elapsed' => '67'],
            ['id' => '2', 'home_score' => '3', 'away_score' => '0', 'finished' => 'TRUE', 'time_elapsed' => 'FT'],
        ];
        $this->repository->save($this->builder->build($games));
        $output->writeln('<info>World Cup snapshot seeded.</info>');
        return Command::SUCCESS;
    }
}
