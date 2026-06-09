<?php
/**
 * Antoine World Cup Hub — upstream REST client. Server-side only; injects the
 * JWT from config and never returns it. v1 reads the full games list.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Model\Upstream;

use Magento\Framework\HTTP\ClientInterface;

class Client
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Constructor.
     *
     * @param ClientInterface $http
     * @param Config          $config
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly Config $config
    ) {
    }

    /**
     * Fetch the full games list from the upstream API.
     *
     * Sets a Bearer token header, performs a GET request, and decodes the
     * JSON array response. The token is never exposed in return values or logs.
     *
     * @return array<int,array<string,mixed>>
     * @throws UpstreamException When the token is absent, the HTTP status is
     *                           not 200, or the payload is not a JSON array.
     */
    public function getGames(): array
    {
        $token = $this->config->getToken();
        if ($token === '') {
            throw new UpstreamException(__('World Cup upstream token is not configured.'));
        }

        // Bounds a hung upstream from stalling the cron job indefinitely.
        $this->http->setTimeout(self::TIMEOUT_SECONDS);
        $this->http->addHeader('Authorization', 'Bearer ' . $token);
        $this->http->get($this->config->getBaseUrl() . '/get/games');

        $status = (int) $this->http->getStatus();
        if ($status !== 200) {
            throw new UpstreamException(__('World Cup upstream returned status %1.', $status));
        }

        $decoded = json_decode((string) $this->http->getBody(), true);
        if (!is_array($decoded)) {
            throw new UpstreamException(__('World Cup upstream returned an invalid payload.'));
        }

        return $decoded;
    }
}
