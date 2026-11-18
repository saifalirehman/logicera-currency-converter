<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Test\Unit\ViewModel;

use Logicera\CurrencyConverter\Model\Config;
use Logicera\CurrencyConverter\ViewModel\Converter;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the converter view model.
 */
class ConverterTest extends TestCase
{
    public function testJsonConfigExposesDefaultsAndEndpoints(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getDefaultFrom')->willReturn('USD');
        $config->method('getDefaultTo')->willReturn('EUR');

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn ($path) => 'http://localhost/' . $path);

        $viewModel = new Converter($config, new Json(), $url);

        $decoded = json_decode($viewModel->getJsonConfig(), true);
        $component = $decoded['Logicera_CurrencyConverter/js/converter'];

        $this->assertSame('USD', $component['defaultFrom']);
        $this->assertSame('EUR', $component['defaultTo']);
        $this->assertSame(
            'http://localhost/currencyconverter/ajax/rate',
            $component['endpoints']['rate']
        );
    }
}
