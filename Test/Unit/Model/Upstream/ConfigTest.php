<?php
declare(strict_types=1);

namespace Antoine\WorldCup\Test\Unit\Model\Upstream;

use Antoine\WorldCup\Model\Upstream\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /** @var ScopeConfigInterface */
    private ScopeConfigInterface $scopeConfig;

    /** @var EncryptorInterface */
    private EncryptorInterface $encryptor;

    /** @var Config */
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->config = new Config($this->scopeConfig, $this->encryptor);
    }

    public function testIsEnabledReadsFlag(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('antoine_worldcup/general/enabled')
            ->willReturn(true);
        $this->assertTrue($this->config->isEnabled());
    }

    public function testGetTokenDecryptsStoredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('antoine_worldcup/general/jwt_token')
            ->willReturn('encrypted-blob');
        $this->encryptor->method('decrypt')->with('encrypted-blob')->willReturn('plain-jwt');
        $this->assertSame('plain-jwt', $this->config->getToken());
    }

    public function testGetPollIntervalFallsBackToFortyFiveOnEmpty(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('antoine_worldcup/general/poll_interval')
            ->willReturn('');
        $this->assertSame(45, $this->config->getPollInterval());
    }

    public function testGetTokenReturnsEmptyStringWhenNotConfigured(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('antoine_worldcup/general/jwt_token')
            ->willReturn('');
        $this->encryptor->expects($this->never())->method('decrypt');
        $this->assertSame('', $this->config->getToken());
    }

    public function testGetCacheTtlReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('antoine_worldcup/general/cache_ttl')
            ->willReturn('120');
        $this->assertSame(120, $this->config->getCacheTtl());
    }

    public function testGetCacheTtlFallsBackToThreeHundredOnEmpty(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('antoine_worldcup/general/cache_ttl')
            ->willReturn('');
        $this->assertSame(300, $this->config->getCacheTtl());
    }

    public function testGetBaseUrlTrimsTrailingSlash(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('antoine_worldcup/general/base_url')
            ->willReturn('https://worldcup26.ir/');
        $this->assertSame('https://worldcup26.ir', $this->config->getBaseUrl());
    }
}
