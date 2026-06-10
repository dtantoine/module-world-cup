<?php
/**
 * Antoine World Cup Hub — upstream REST client. Server-side only; never
 * returns the JWT. v1 reads the full games list.
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
     * The JWT is optional — sent only when configured, in case auth is later
     * enforced. The token is never exposed in return values or logs.
     * Accepts both a bare JSON array and the `{"games":[...]}` envelope that
     * the live worldcup26.ir API currently returns.
     *
     * @return array<int,array<string,mixed>>
     * @throws UpstreamException When the HTTP status is not 200 or the payload
     *                           cannot be decoded into a recognised structure.
     */
    public function getGames(): array
    {
        // The live upstream currently serves /get/games publicly, so the JWT is
        // OPTIONAL — send it only when configured (in case auth is later enforced).
        $this->http->setTimeout(self::TIMEOUT_SECONDS);
        $token = $this->config->getToken();
        if ($token !== '') {
            $this->http->addHeader('Authorization', 'Bearer ' . $token);
        }

        $this->http->get($this->config->getBaseUrl() . '/get/games');

        $status = (int) $this->http->getStatus();
        if ($status !== 200) {
            throw new UpstreamException(__('World Cup upstream returned status %1.', $status));
        }

        $decoded = json_decode((string) $this->http->getBody(), true);
        if (!is_array($decoded)) {
            throw new UpstreamException(__('World Cup upstream returned an invalid payload.'));
        }

        // The live API wraps the list as {"games":[...]}; accept that or a bare array.
        if (isset($decoded['games']) && is_array($decoded['games'])) {
            return $decoded['games'];
        }
        if (array_is_list($decoded)) {
            return $decoded;
        }

        throw new UpstreamException(__('World Cup upstream returned an unrecognized payload.'));
    }
}
