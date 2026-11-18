<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Test\Unit\Controller\Ajax;

use Logicera\CurrencyConverter\Api\FrankfurterServiceInterface;
use Logicera\CurrencyConverter\Controller\Ajax\Currencies;
use Logicera\CurrencyConverter\Model\Config;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the shared AJAX controller behaviour (enabled check + envelopes).
 */
class CurrenciesTest extends TestCase
{
    /**
     * @var int|null
     */
    private $capturedCode;

    /**
     * @var array|null
     */
    private $capturedData;

    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var FrankfurterServiceInterface&MockObject
     */
    private $service;

    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * @var Currencies
     */
    private $controller;

    protected function setUp(): void
    {
        $this->capturedCode = null;
        $this->capturedData = null;

        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->willReturnCallback(function ($code) use ($result) {
            $this->capturedCode = $code;
            return $result;
        });
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->capturedData = $data;
            return $result;
        });

        $resultFactory = $this->createMock(JsonFactory::class);
        $resultFactory->method('create')->willReturn($result);

        $this->config = $this->createMock(Config::class);
        $this->service = $this->createMock(FrankfurterServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new Currencies($resultFactory, $this->logger, $this->config, $this->service);
    }

    public function testDisabledFeatureReturns404(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->service->expects($this->never())->method('getCurrencies');

        $this->controller->execute();

        $this->assertSame(404, $this->capturedCode);
        $this->assertFalse($this->capturedData['success']);
    }

    public function testSuccessReturnsData(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->service->method('getCurrencies')->willReturn(['USD' => 'US Dollar']);

        $this->controller->execute();

        $this->assertNull($this->capturedCode);
        $this->assertTrue($this->capturedData['success']);
        $this->assertSame(['USD' => 'US Dollar'], $this->capturedData['currencies']);
    }

    public function testLocalizedExceptionReturns422WithMessage(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->service->method('getCurrencies')
            ->willThrowException(new LocalizedException(__('bad input')));

        $this->controller->execute();

        $this->assertSame(422, $this->capturedCode);
        $this->assertFalse($this->capturedData['success']);
        $this->assertSame('bad input', $this->capturedData['message']);
    }

    public function testUnexpectedExceptionReturns500AndLogs(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->service->method('getCurrencies')
            ->willThrowException(new \RuntimeException('boom'));
        $this->logger->expects($this->once())->method('error');

        $this->controller->execute();

        $this->assertSame(500, $this->capturedCode);
        $this->assertFalse($this->capturedData['success']);
    }
}
