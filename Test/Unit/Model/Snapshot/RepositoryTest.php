<?php
/**
 * Antoine World Cup Hub — unit tests for Snapshot\Repository resilience ladder.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Test\Unit\Model\Snapshot;

use Antoine\WorldCup\Model\Cache\Type\Snapshot;
use Antoine\WorldCup\Model\Snapshot\Builder;
use Antoine\WorldCup\Model\Snapshot\Repository;
use Antoine\WorldCup\Model\Upstream\Config;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
    /** @var Snapshot */
    private Snapshot $cache;

    /** @var Builder */
    private Builder $builder;

    /** @var Config */
    private Config $config;

    /** @var Repository */
    private Repository $repo;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(Snapshot::class);
        $this->builder = $this->createMock(Builder::class);
        $this->config = $this->createMock(Config::class);
        $this->config->method('getCacheTtl')->willReturn(300);
        $this->repo = new Repository($this->cache, $this->builder, $this->config);
    }

    public function testGetReturnsCachedSnapshotWhenPresent(): void
    {
        $this->cache->method('load')->with(Repository::CACHE_KEY)
            ->willReturn(json_encode(['any_live' => true, 'stale' => false]));
        $this->builder->expects($this->never())->method('build');
        $this->assertTrue($this->repo->get()['any_live']);
    }

    public function testGetFallsBackToStaticStaleSnapshotWhenCacheEmpty(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->builder->method('build')->with([])->willReturn(['any_live' => false, 'stale' => false]);
        $result = $this->repo->get();
        $this->assertTrue($result['stale']);
    }

    public function testSaveStoresJsonWithTtlAndTag(): void
    {
        $snap = ['any_live' => false];
        $this->cache->expects($this->once())->method('save')
            ->with(json_encode($snap), Repository::CACHE_KEY, [Snapshot::CACHE_TAG], 300);
        $this->repo->save($snap);
    }
}
