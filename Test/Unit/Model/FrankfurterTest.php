<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Test\Unit\Model;

use Logicera\CurrencyConverter\Model\Config;
use Logicera\CurrencyConverter\Model\Frankfurter;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the Frankfurter API client.
 */
class FrankfurterTest extends TestCase
{
    /**
     * @var ClientInterface&MockObject
     */
    private $curl;

    /**
     * @var CacheInterface&MockObject
     */
    private $cache;

    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var Frankfurter
     */
    private $service;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(ClientInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->config = $this->createMock(Config::class);

        $this->config->method('getApiBaseUrl')->willReturn('https://api.frankfurter.dev/v1');
        $this->config->method('getTimeout')->willReturn(10);
        $this->config->method('getCacheLifetime')->willReturn(3600);
        $this->config->method('getRateLimit')->willReturn(0);

        $this->service = new Frankfurter(
            $this->curl,
            new Json(),
            $this->cache,
            $this->createMock(LoggerInterface::class),
            $this->config
        );
    }

    public function testIdenticalCurrencyReturnsParityWithoutCallingApi(): void
    {
        $this->curl->expects($this->never())->method('get');

        $result = $this->service->getLatestRate('USD', 'usd');

        $this->assertSame(1.0, $result['rate']);
        $this->assertSame('USD', $result['base']);
        $this->assertSame('USD', $result['target']);
    }

    public function testInvalidCurrencyCodeThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->service->getLatestRate('US', 'EUR');
    }

    public function testGetLatestRateReturnsRateFromApi(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn(
            '{"amount":1.0,"base":"USD","date":"2026-11-18","rates":{"EUR":0.92}}'
        );

        $result = $this->service->getLatestRate('USD', 'EUR');

        $this->assertSame(0.92, $result['rate']);
        $this->assertSame('2026-11-18', $result['date']);
    }

    public function testCachedResponseSkipsHttpCall(): void
    {
        $this->curl->expects($this->never())->method('get');
        $this->cache->method('load')->willReturn(
            (new Json())->serialize(['base' => 'USD', 'date' => '2026-11-18', 'rates' => ['EUR' => 0.8]])
        );

        $result = $this->service->getLatestRate('USD', 'EUR');

        $this->assertSame(0.8, $result['rate']);
    }

    public function testRateLimitBlocksCallWhenExceeded(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getApiBaseUrl')->willReturn('https://api.frankfurter.dev/v1');
        $config->method('getTimeout')->willReturn(10);
        $config->method('getCacheLifetime')->willReturn(3600);
        $config->method('getRateLimit')->willReturn(1);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(
            static fn ($key) => strpos((string)$key, 'ratelimit') !== false ? '1' : false
        );

        $curl = $this->createMock(ClientInterface::class);
        $curl->expects($this->never())->method('get');

        $service = new Frankfurter(
            $curl,
            new Json(),
            $cache,
            $this->createMock(LoggerInterface::class),
            $config
        );

        $this->expectException(LocalizedException::class);
        $service->getLatestRate('USD', 'EUR');
    }

    public function testNonSuccessStatusIsReportedCleanly(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('getStatus')->willReturn(503);

        $this->expectException(LocalizedException::class);
        $this->service->getLatestRate('USD', 'EUR');
    }

    public function testGetCurrenciesAreSortedByName(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"USD":"US Dollar","AUD":"Australian Dollar"}');

        $this->assertSame(
            ['AUD' => 'Australian Dollar', 'USD' => 'US Dollar'],
            $this->service->getCurrencies()
        );
    }

    public function testHistoryIdentityReturnsFlatSeries(): void
    {
        $this->curl->expects($this->never())->method('get');

        $this->assertSame(
            ['2026-11-01' => 1.0, '2026-11-02' => 1.0, '2026-11-03' => 1.0],
            $this->service->getHistory('USD', 'USD', '2026-11-01', '2026-11-03')
        );
    }

    public function testHistoryParsesAndSortsSeries(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn(
            '{"base":"USD","rates":{"2026-11-02":{"EUR":0.9},"2026-11-01":{"EUR":0.8}}}'
        );

        $this->assertSame(
            ['2026-11-01' => 0.8, '2026-11-02' => 0.9],
            $this->service->getHistory('USD', 'EUR', '2026-11-01', '2026-11-02')
        );
    }

    public function testNonHttpBaseUrlIsRejected(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getApiBaseUrl')->willReturn('file:///etc/passwd');
        $config->method('getTimeout')->willReturn(10);
        $config->method('getCacheLifetime')->willReturn(3600);
        $config->method('getRateLimit')->willReturn(0);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);

        $service = new Frankfurter(
            $this->createMock(ClientInterface::class),
            new Json(),
            $cache,
            $this->createMock(LoggerInterface::class),
            $config
        );

        $this->expectException(LocalizedException::class);
        $service->getCurrencies();
    }

    public function testNonArrayResponseIsRejected(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('42');

        $this->expectException(LocalizedException::class);
        $this->service->getCurrencies();
    }

    public function testNetworkExceptionIsWrappedAsLocalized(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('get')->willThrowException(new \RuntimeException('network down'));

        $this->expectException(LocalizedException::class);
        $this->service->getCurrencies();
    }
}
