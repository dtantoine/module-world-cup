<?php
/**
 * Antoine World Cup Hub — typed accessor over the module's store config.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Model\Upstream;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    private const XML_ENABLED = 'antoine_worldcup/general/enabled';
    private const XML_BASE_URL = 'antoine_worldcup/general/base_url';
    private const XML_TOKEN = 'antoine_worldcup/general/jwt_token';
    private const XML_POLL_INTERVAL = 'antoine_worldcup/general/poll_interval';
    private const XML_CACHE_TTL = 'antoine_worldcup/general/cache_ttl';

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface   $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Whether the upstream integration is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED);
    }

    /**
     * Upstream base URL with trailing slash stripped.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return rtrim((string) $this->scopeConfig->getValue(self::XML_BASE_URL), '/');
    }

    /**
     * Decrypted JWT bearer token; empty string when not configured.
     *
     * @return string
     */
    public function getToken(): string
    {
        $stored = (string) $this->scopeConfig->getValue(self::XML_TOKEN);
        return $stored === '' ? '' : (string) $this->encryptor->decrypt($stored);
    }

    /**
     * Frontend live-poll interval in seconds (default 60).
     *
     * @return int
     */
    public function getPollInterval(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_POLL_INTERVAL);
        return $value > 0 ? $value : 60;
    }

    /**
     * Snapshot cache TTL in seconds (default 300).
     *
     * @return int
     */
    public function getCacheTtl(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_CACHE_TTL);
        return $value > 0 ? $value : 300;
    }
}
