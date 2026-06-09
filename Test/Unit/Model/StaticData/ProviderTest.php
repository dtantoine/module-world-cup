<?php
declare(strict_types=1);

namespace Antoine\WorldCup\Test\Unit\Model\StaticData;

use Antoine\WorldCup\Model\StaticData\Provider;
use Magento\Framework\Module\Dir\Reader;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase
{
    /** @var string */
    private string $dir;

    /** @var Provider */
    private Provider $provider;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wc_' . uniqid();
        mkdir($this->dir . '/Model/Data', 0777, true);
        file_put_contents(
            $this->dir . '/Model/Data/teams.json',
            json_encode([
                ['id' => '1', 'name' => 'Qatar', 'code' => 'QAT', 'group' => 'A', 'flag' => 'q.png'],
                ['id' => '2', 'name' => 'Ecuador', 'code' => 'ECU', 'group' => 'A', 'flag' => 'e.png'],
            ])
        );
        file_put_contents(
            $this->dir . '/Model/Data/stadiums.json',
            json_encode([
                [
                    'id'       => '1',
                    'name'     => 'Lumen Field',
                    'city'     => 'Seattle',
                    'country'  => 'United States',
                    'capacity' => 69000,
                    'timezone' => 'America/Los_Angeles',
                ],
            ])
        );
        file_put_contents(
            $this->dir . '/Model/Data/fixtures.json',
            json_encode([
                [
                    'id'              => '1',
                    'home_team_id'    => '1',
                    'away_team_id'    => '2',
                    'home_team_label' => '',
                    'away_team_label' => '',
                    'group'           => 'A',
                    'matchday'        => '1',
                    'local_date'      => '06/11/2026 13:00',
                    'stadium_id'      => '1',
                    'type'            => 'group',
                ],
            ])
        );
        file_put_contents(
            $this->dir . '/Model/Data/groups.json',
            json_encode(['A' => ['1', '2']])
        );

        $reader = $this->createMock(Reader::class);
        $reader->method('getModuleDir')->with('', 'Antoine_WorldCup')->willReturn($this->dir);
        $this->provider = new Provider($reader);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/Model/Data/*.json'));
        rmdir($this->dir . '/Model/Data');
        rmdir($this->dir . '/Model');
        rmdir($this->dir);
    }

    public function testGetTeamByIdReturnsResolvedRecord(): void
    {
        $this->assertSame('Qatar', $this->provider->getTeam('1')['name']);
        $this->assertSame('q.png', $this->provider->getTeam('1')['flag']);
    }

    public function testGetTeamUnknownIdReturnsNull(): void
    {
        $this->assertNull($this->provider->getTeam('999'));
    }

    public function testGetStadiumTimezone(): void
    {
        $this->assertSame('America/Los_Angeles', $this->provider->getStadium('1')['timezone']);
    }

    public function testGetFixturesReturnsAll(): void
    {
        $this->assertCount(1, $this->provider->getFixtures());
    }

    public function testGetGroupsReturnsMembership(): void
    {
        $this->assertSame(['1', '2'], $this->provider->getGroups()['A']);
    }
}
