<?php
declare(strict_types=1);

namespace Antoine\WorldCup\Test\Unit\Cron;

use Antoine\WorldCup\Cron\RefreshSnapshot;
use Antoine\WorldCup\Model\Snapshot\Builder;
use Antoine\WorldCup\Model\Snapshot\Repository;
use Antoine\WorldCup\Model\Upstream\Client;
use Antoine\WorldCup\Model\Upstream\Config;
use Antoine\WorldCup\Model\Upstream\UpstreamException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class RefreshSnapshotTest extends TestCase
{
    /** @var Config */
    private Config $config;

    /** @var Client */
    private Client $client;

    /** @var Builder */
    private Builder $builder;

    /** @var Repository */
    private Repository $repo;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var RefreshSnapshot */
    private RefreshSnapshot $cron;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->client = $this->createMock(Client::class);
        $this->builder = $this->createMock(Builder::class);
        $this->repo = $this->createMock(Repository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cron = new RefreshSnapshot($this->config, $this->client, $this->builder, $this->repo, $this->logger);
    }

    public function testDisabledDoesNothing(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->client->expects($this->never())->method('getGames');
        $this->repo->expects($this->never())->method('save');
        $this->cron->execute();
    }

    public function testEnabledBuildsAndSavesSnapshot(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->client->method('getGames')->willReturn([['id' => '1']]);
        $this->builder->method('build')->with([['id' => '1']])->willReturn(['any_live' => true]);
        $this->repo->expects($this->once())->method('save')->with(['any_live' => true]);
        $this->cron->execute();
    }

    public function testUpstreamFailureLogsAndPreservesSnapshot(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->client->method('getGames')->willThrowException(new UpstreamException(__('boom')));
        $this->repo->expects($this->never())->method('save');
        $this->logger->expects($this->once())->method('error');
        $this->cron->execute();
    }
}
