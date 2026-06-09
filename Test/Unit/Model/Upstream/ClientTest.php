<?php
/**
 * Antoine World Cup Hub — unit tests for the upstream REST client.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Test\Unit\Model\Upstream;

use Antoine\WorldCup\Model\Upstream\Client;
use Antoine\WorldCup\Model\Upstream\Config;
use Antoine\WorldCup\Model\Upstream\UpstreamException;
use Magento\Framework\HTTP\ClientInterface;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /** @var ClientInterface */
    private ClientInterface $http;

    /** @var Config */
    private Config $config;

    /** @var Client */
    private Client $client;

    protected function setUp(): void
    {
        $this->http = $this->createMock(ClientInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->config->method('getBaseUrl')->willReturn('https://worldcup26.ir');
        $this->config->method('getToken')->willReturn('plain-jwt');
        $this->client = new Client($this->http, $this->config);
    }

    public function testGetGamesSendsBearerAndParsesArray(): void
    {
        $this->http->expects($this->once())->method('addHeader')
            ->with('Authorization', 'Bearer plain-jwt');
        $this->http->expects($this->once())->method('get')
            ->with('https://worldcup26.ir/get/games');
        $this->http->method('getStatus')->willReturn(200);
        $this->http->method('getBody')->willReturn('[{"id":"1"}]');

        $this->assertSame([['id' => '1']], $this->client->getGames());
    }

    public function testGetGamesThrowsOnNon200(): void
    {
        $this->http->method('getStatus')->willReturn(429);
        $this->http->method('getBody')->willReturn('rate limited');
        $this->expectException(UpstreamException::class);
        $this->client->getGames();
    }

    public function testGetGamesThrowsOnInvalidJson(): void
    {
        $this->http->method('getStatus')->willReturn(200);
        $this->http->method('getBody')->willReturn('not json');
        $this->expectException(UpstreamException::class);
        $this->client->getGames();
    }

    public function testGetGamesThrowsWhenTokenMissing(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getToken')->willReturn('');
        $this->http->expects($this->never())->method('addHeader');
        $this->http->expects($this->never())->method('get');
        $client = new Client($this->http, $config);
        $this->expectException(UpstreamException::class);
        $client->getGames();
    }

    public function testGetGamesThrowsOnNonArrayJson(): void
    {
        $this->http->method('getStatus')->willReturn(200);
        $this->http->method('getBody')->willReturn('"just a string"');
        $this->expectException(UpstreamException::class);
        $this->client->getGames();
    }
}
