<?php
/**
 * Antoine World Cup Hub — loads bundled static reference JSON and indexes it by
 * id. Memoized for the request lifetime; the single owner of the file layout.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Model\StaticData;

use Magento\Framework\Module\Dir\Reader;

class Provider
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $teams = null;
    /** @var array<string,array<string,mixed>>|null */
    private ?array $stadiums = null;
    /** @var array<int,array<string,mixed>>|null */
    private ?array $fixtures = null;
    /** @var array<string,array<int,string>>|null */
    private ?array $groups = null;

    /**
     * @param Reader $moduleReader
     */
    public function __construct(private readonly Reader $moduleReader)
    {
    }

    /**
     * Return a team record by id, or null if not found.
     *
     * @param string $id
     * @return array<string,mixed>|null
     */
    public function getTeam(string $id): ?array
    {
        $this->loadTeams();
        return $this->teams[$id] ?? null;
    }

    /**
     * Return a stadium record by id, or null if not found.
     *
     * @param string $id
     * @return array<string,mixed>|null
     */
    public function getStadium(string $id): ?array
    {
        $this->loadStadiums();
        return $this->stadiums[$id] ?? null;
    }

    /**
     * Return all fixture records.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getFixtures(): array
    {
        if ($this->fixtures === null) {
            $this->fixtures = $this->read('fixtures.json');
        }
        return $this->fixtures;
    }

    /**
     * Return all groups with their member team ids.
     *
     * @return array<string,array<int,string>>
     */
    public function getGroups(): array
    {
        if ($this->groups === null) {
            $this->groups = $this->read('groups.json');
        }
        return $this->groups;
    }

    /**
     * Load and index teams by id (memoized).
     *
     * @return void
     */
    private function loadTeams(): void
    {
        if ($this->teams === null) {
            $this->teams = [];
            foreach ($this->read('teams.json') as $row) {
                $this->teams[(string) $row['id']] = $row;
            }
        }
    }

    /**
     * Load and index stadiums by id (memoized).
     *
     * @return void
     */
    private function loadStadiums(): void
    {
        if ($this->stadiums === null) {
            $this->stadiums = [];
            foreach ($this->read('stadiums.json') as $row) {
                $this->stadiums[(string) $row['id']] = $row;
            }
        }
    }

    /**
     * Read and decode a JSON file from the module's Model/Data directory.
     *
     * @param string $file
     * @return array<mixed>
     */
    private function read(string $file): array
    {
        $path = $this->moduleReader->getModuleDir('', 'Antoine_WorldCup') . '/Model/Data/' . $file;
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}
