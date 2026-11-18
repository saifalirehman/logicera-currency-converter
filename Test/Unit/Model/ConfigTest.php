<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Test\Unit\Model;

use Logicera\CurrencyConverter\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the configuration accessor.
 */
class ConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var Config
     */
    private $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledReadsFlagAtStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('currency_converter/general/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    public function testHistoryDaysIsClampedToUpperBound(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('5000');
        $this->assertSame(365, $this->config->getHistoryDays());
    }

    public function testHistoryDaysIsClampedToLowerBound(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('0');
        $this->assertSame(1, $this->config->getHistoryDays());
    }

    public function testApiBaseUrlTrailingSlashIsTrimmed(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('https://api.frankfurter.dev/v1/');
        $this->assertSame('https://api.frankfurter.dev/v1', $this->config->getApiBaseUrl());
    }

    public function testDefaultFromIsUppercased(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('usd');
        $this->assertSame('USD', $this->config->getDefaultFrom());
    }
}
