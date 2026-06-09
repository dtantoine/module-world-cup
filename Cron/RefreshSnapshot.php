<?php
/**
 * Antoine World Cup Hub — refreshes the live snapshot from upstream. No-ops when
 * disabled; preserves the last good snapshot on upstream failure.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Cron;

use Antoine\WorldCup\Model\Snapshot\Builder;
use Antoine\WorldCup\Model\Snapshot\Repository;
use Antoine\WorldCup\Model\Upstream\Client;
use Antoine\WorldCup\Model\Upstream\Config;
use Antoine\WorldCup\Model\Upstream\UpstreamException;
use Psr\Log\LoggerInterface;

class RefreshSnapshot
{
    /**
     * Constructor.
     *
     * @param Config          $config
     * @param Client          $client
     * @param Builder         $builder
     * @param Repository      $repository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly Client $client,
        private readonly Builder $builder,
        private readonly Repository $repository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Refresh the live snapshot from upstream.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        try {
            $games = $this->client->getGames();
            $this->repository->save($this->builder->build($games));
        } catch (UpstreamException $e) {
            $this->logger->error('World Cup snapshot refresh failed: ' . $e->getMessage());
        }
    }
}
