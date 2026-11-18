<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Test\Integration\Model;

use Logicera\CurrencyConverter\Api\FrankfurterServiceInterface;
use Logicera\CurrencyConverter\Model\Config;
use Logicera\CurrencyConverter\Model\Frankfurter;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the module is wired correctly inside a real Magento instance:
 * the service contract resolves to our implementation and the shipped
 * configuration defaults are readable. No external API call is made.
 */
class ServiceWiringTest extends TestCase
{
    public function testServiceContractResolvesToFrankfurter(): void
    {
        $service = Bootstrap::getObjectManager()->get(FrankfurterServiceInterface::class);
        $this->assertInstanceOf(Frankfurter::class, $service);
    }

    public function testConfigReturnsShippedDefaults(): void
    {
        $config = Bootstrap::getObjectManager()->get(Config::class);

        $this->assertSame('USD', $config->getDefaultFrom());
        $this->assertSame('EUR', $config->getDefaultTo());
        $this->assertSame('https://api.frankfurter.dev/v1', $config->getApiBaseUrl());
        $this->assertSame(30, $config->getHistoryDays());
    }
}
