<?php
/**
 * Antoine World Cup Hub — unit tests for Snapshot\Builder (part 1):
 * status bucketing, kickoff timezone conversion, team/stadium resolution.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Test\Unit\Model\Snapshot;

use Antoine\WorldCup\Model\Snapshot\Builder;
use Antoine\WorldCup\Model\StaticData\Provider;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    /** @var Provider */
    private Provider $provider;

    /** @var Builder */
    private Builder $builder;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(Provider::class);
        $this->provider->method('getTeam')->willReturnCallback(fn ($id) => match ($id) {
            '1' => ['id' => '1', 'name' => 'Qatar', 'code' => 'QAT', 'flag' => 'q.png'],
            '2' => ['id' => '2', 'name' => 'Ecuador', 'code' => 'ECU', 'flag' => 'e.png'],
            default => null,
        });
        $this->provider->method('getStadium')->willReturn([
            'id' => '1', 'name' => 'Lumen Field', 'city' => 'Seattle',
            'country' => 'United States', 'timezone' => 'America/Los_Angeles',
        ]);
        $this->provider->method('getFixtures')->willReturn([
            [
                'id' => '1', 'home_team_id' => '1', 'away_team_id' => '2',
                'home_team_label' => '', 'away_team_label' => '',
                'group' => 'A', 'matchday' => '1',
                'local_date' => '06/11/2026 13:00', 'stadium_id' => '1', 'type' => 'group',
            ],
        ]);
        $this->provider->method('getGroups')->willReturn(['A' => ['1', '2']]);
        $this->builder = new Builder($this->provider);
    }

    public function testUpcomingMatchHasResolvedTeamsAndBeirutKickoff(): void
    {
        $snap = $this->builder->build([
            [
                'id' => '1', 'home_score' => '0', 'away_score' => '0',
                'finished' => 'FALSE', 'time_elapsed' => 'notstarted',
            ],
        ]);
        $this->assertCount(1, $snap['matches']['upcoming']);
        $m = $snap['matches']['upcoming'][0];
        $this->assertSame('Qatar', $m['home']['name']);
        $this->assertSame('Ecuador', $m['away']['name']);
        $this->assertSame('Lumen Field', $m['stadium']['name']);
        // 13:00 Seattle (PDT, UTC-7) = 23:00 Beirut (EEST, UTC+3)
        $this->assertSame('Thu 11 Jun, 23:00', $m['kickoff_beirut']);
        $this->assertSame('—', $m['home_score']);
        $this->assertSame('—', $m['away_score']);
    }

    public function testLiveMatchBucketedLiveWithScore(): void
    {
        $snap = $this->builder->build([
            ['id' => '1', 'home_score' => '2', 'away_score' => '1', 'finished' => 'FALSE', 'time_elapsed' => '67'],
        ]);
        $this->assertCount(1, $snap['matches']['live']);
        $this->assertSame('2', $snap['matches']['live'][0]['home_score']);
        $this->assertSame('live', $snap['matches']['live'][0]['status']);
        $this->assertTrue($snap['any_live']);
    }

    public function testFinishedMatchBucketedFinished(): void
    {
        $snap = $this->builder->build([
            ['id' => '1', 'home_score' => '3', 'away_score' => '1', 'finished' => 'TRUE', 'time_elapsed' => 'FT'],
        ]);
        $this->assertCount(1, $snap['matches']['finished']);
        $this->assertFalse($snap['any_live']);
    }

    public function testMissingLiveDataFallsBackToStaticUpcomingWithDashScore(): void
    {
        $snap = $this->builder->build([]); // no live games at all
        $this->assertCount(1, $snap['matches']['upcoming']);
        $this->assertSame('—', $snap['matches']['upcoming'][0]['home_score']);
    }

    public function testUnknownTeamYieldsEmptyNameStruct(): void
    {
        $provider = $this->createMock(Provider::class);
        $provider->method('getTeam')->willReturn(null);
        $provider->method('getStadium')->willReturn(['timezone' => 'America/Los_Angeles']);
        $provider->method('getGroups')->willReturn([]);
        $provider->method('getFixtures')->willReturn([
            [
                'id' => '9', 'home_team_id' => '999', 'away_team_id' => '998',
                'home_team_label' => '', 'away_team_label' => '',
                'group' => 'A', 'matchday' => '1',
                'local_date' => '06/11/2026 13:00', 'stadium_id' => '1', 'type' => 'group',
            ],
        ]);
        $snap = (new Builder($provider))->build([]);
        $m = $snap['matches']['upcoming'][0];
        $this->assertSame('', $m['home']['name']);
        $this->assertSame('999', $m['home']['id']);
    }

    public function testMissingStadiumYieldsEmptyStadiumFields(): void
    {
        $provider = $this->createMock(Provider::class);
        $provider->method('getTeam')->willReturn(
            ['id' => '1', 'name' => 'Qatar', 'code' => 'QAT', 'flag' => 'q.png']
        );
        $provider->method('getStadium')->willReturn(null);
        $provider->method('getGroups')->willReturn([]);
        $provider->method('getFixtures')->willReturn([
            [
                'id' => '9', 'home_team_id' => '1', 'away_team_id' => '1',
                'home_team_label' => '', 'away_team_label' => '',
                'group' => 'A', 'matchday' => '1',
                'local_date' => '06/11/2026 13:00', 'stadium_id' => '404', 'type' => 'group',
            ],
        ]);
        $snap = (new Builder($provider))->build([]);
        $m = $snap['matches']['upcoming'][0];
        $this->assertSame('', $m['stadium']['name']);
        $this->assertIsString($m['kickoff_beirut']);
    }
}
