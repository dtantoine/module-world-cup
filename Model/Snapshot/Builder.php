<?php
/**
 * Antoine World Cup Hub — normalizes live games merged over static fixtures
 * into the snapshot consumed by the page + JSON endpoint. The single owner of
 * status bucketing, kickoff timezone conversion, and (next task) standings/bracket.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Model\Snapshot;

use Antoine\WorldCup\Model\StaticData\Provider;

class Builder
{
    private const BEIRUT_TZ = 'Asia/Beirut';

    /**
     * @param Provider $provider
     */
    public function __construct(private readonly Provider $provider)
    {
    }

    /**
     * Merge live games over static fixtures and return a normalized snapshot.
     *
     * @param array<int,array<string,mixed>> $games live games keyed arbitrarily
     * @return array<string,mixed>
     */
    public function build(array $games): array
    {
        $liveById = [];
        foreach ($games as $game) {
            if (isset($game['id'])) {
                $liveById[(string) $game['id']] = $game;
            }
        }

        $buckets = ['live' => [], 'upcoming' => [], 'finished' => []];
        $anyLive = false;
        $normalized = [];

        foreach ($this->provider->getFixtures() as $fixture) {
            $match = $this->normalizeMatch($fixture, $liveById[(string) $fixture['id']] ?? null);
            $normalized[] = $match;
            $buckets[$match['status']][] = $match;
            if ($match['status'] === 'live') {
                $anyLive = true;
            }
        }

        return [
            'generated_at' => $this->nowBeirutIso(),
            'any_live' => $anyLive,
            'stale' => false,
            'matches' => $buckets,
            'standings' => $this->buildStandings($normalized),
            'bracket' => $this->buildBracket($normalized),
        ];
    }

    /**
     * Normalize a single fixture record merged with optional live data.
     *
     * @param array<string,mixed> $fixture
     * @param array<string,mixed>|null $live
     * @return array<string,mixed>
     */
    private function normalizeMatch(array $fixture, ?array $live): array
    {
        $status = $this->status($live);
        $isPreGame = $live === null || $status === 'upcoming';
        $homeScore = $isPreGame ? '—' : (string) ($live['home_score'] ?? '0');
        $awayScore = $isPreGame ? '—' : (string) ($live['away_score'] ?? '0');
        $stadium = $this->provider->getStadium((string) ($fixture['stadium_id'] ?? '')) ?? [];

        return [
            'id' => (string) $fixture['id'],
            'type' => (string) $fixture['type'],
            'group' => (string) ($fixture['group'] ?? ''),
            'matchday' => (string) ($fixture['matchday'] ?? ''),
            'home' => $this->team((string) ($fixture['home_team_id'] ?? '0')),
            'away' => $this->team((string) ($fixture['away_team_id'] ?? '0')),
            'home_label' => (string) ($fixture['home_team_label'] ?? ''),
            'away_label' => (string) ($fixture['away_team_label'] ?? ''),
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'status' => $status,
            'time_elapsed' => (string) ($live['time_elapsed'] ?? ''),
            'stadium' => [
                'name' => (string) ($stadium['name'] ?? ''),
                'city' => (string) ($stadium['city'] ?? ''),
                'country' => (string) ($stadium['country'] ?? ''),
            ],
            'kickoff_iso' => $this->kickoffIso($fixture, $stadium),
            'kickoff_beirut' => $this->kickoffBeirut($fixture, $stadium),
        ];
    }

    /**
     * Derive the status bucket from live data.
     *
     * @param array<string,mixed>|null $live
     * @return string
     */
    private function status(?array $live): string
    {
        if ($live === null) {
            return 'upcoming';
        }
        // Upstream sends string literals: finished === 'TRUE'/'FALSE', and
        // time_elapsed === 'notstarted' until kickoff (then an elapsed value).
        if ((string) ($live['finished'] ?? 'FALSE') === 'TRUE') {
            return 'finished';
        }
        $elapsed = (string) ($live['time_elapsed'] ?? 'notstarted');
        return ($elapsed !== '' && $elapsed !== 'notstarted') ? 'live' : 'upcoming';
    }

    /**
     * Resolve a team by id to a normalized array.
     *
     * @param string $id
     * @return array<string,string>
     */
    private function team(string $id): array
    {
        $team = $this->provider->getTeam($id);
        if ($team === null) {
            return ['id' => $id, 'name' => '', 'code' => '', 'flag' => ''];
        }
        return [
            'id' => (string) $team['id'],
            'name' => (string) $team['name'],
            'code' => (string) $team['code'],
            'flag' => (string) ($team['flag'] ?? ''),
        ];
    }

    /**
     * Parse local_date in the stadium's IANA timezone into a DateTimeImmutable.
     *
     * @param array<string,mixed> $fixture
     * @param array<string,mixed> $stadium
     * @return \DateTimeImmutable|null
     */
    private function kickoffDate(array $fixture, array $stadium): ?\DateTimeImmutable
    {
        $tz = (string) ($stadium['timezone'] ?? 'UTC');
        $raw = (string) ($fixture['local_date'] ?? '');
        $dt = \DateTimeImmutable::createFromFormat('m/d/Y H:i', $raw, new \DateTimeZone($tz));
        return $dt ?: null;
    }

    /**
     * Return the kickoff as an ISO 8601 string in the stadium's local timezone.
     *
     * @param array<string,mixed> $fixture
     * @param array<string,mixed> $stadium
     * @return string
     */
    private function kickoffIso(array $fixture, array $stadium): string
    {
        $dt = $this->kickoffDate($fixture, $stadium);
        return $dt ? $dt->format(\DateTimeInterface::ATOM) : '';
    }

    /**
     * Return the kickoff formatted in Asia/Beirut for display ("D j M, H:i").
     *
     * @param array<string,mixed> $fixture
     * @param array<string,mixed> $stadium
     * @return string
     */
    private function kickoffBeirut(array $fixture, array $stadium): string
    {
        $dt = $this->kickoffDate($fixture, $stadium);
        if ($dt === null) {
            return '';
        }
        return $dt->setTimezone(new \DateTimeZone(self::BEIRUT_TZ))->format('D j M, H:i');
    }

    /**
     * Current timestamp in Beirut as ISO 8601.
     *
     * @return string
     */
    private function nowBeirutIso(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::BEIRUT_TZ)))
            ->format(\DateTimeInterface::ATOM);
    }

    /**
     * Build group standings from normalized matches (stub — implemented next task).
     *
     * @param array<int,array<string,mixed>> $matches
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function buildStandings(array $matches): array
    {
        return []; // implemented in the next task
    }

    /**
     * Build knockout bracket from normalized matches (stub — implemented next task).
     *
     * @param array<int,array<string,mixed>> $matches
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function buildBracket(array $matches): array
    {
        return []; // implemented in the next task
    }
}
