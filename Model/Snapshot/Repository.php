<?php
/**
 * Antoine World Cup Hub — snapshot cache read/write with the resilience ladder:
 * cached snapshot → static-only fallback (stale) so the page always renders.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Model\Snapshot;

use Antoine\WorldCup\Model\Cache\Type\Snapshot;
use Antoine\WorldCup\Model\Upstream\Config;

class Repository
{
    public const CACHE_KEY = 'worldcup_snapshot_current';

    /**
     * @param Snapshot $cache
     * @param Builder  $builder
     * @param Config   $config
     */
    public function __construct(
        private readonly Snapshot $cache,
        private readonly Builder $builder,
        private readonly Config $config
    ) {
    }

    /**
     * Return the current snapshot from cache, or a stale static-only fallback.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        $raw = $this->cache->load(self::CACHE_KEY);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $static = $this->builder->build([]);
        $static['stale'] = true;
        return $static;
    }

    /**
     * Persist a snapshot to the dedicated cache type with the configured TTL.
     *
     * @param array<string,mixed> $snapshot
     */
    public function save(array $snapshot): void
    {
        $this->cache->save(
            json_encode($snapshot),
            self::CACHE_KEY,
            [Snapshot::CACHE_TAG],
            $this->config->getCacheTtl()
        );
    }
}
