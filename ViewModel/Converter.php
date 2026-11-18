<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\ViewModel;

use Logicera\CurrencyConverter\Model\Config;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * View model for the converter page. Supplies the JS widget its defaults and the
 * AJAX endpoint URLs as a single JSON blob. No exchange data is fetched at render
 * time; the page pulls that from this module's own endpoints.
 */
class Converter implements ArgumentInterface
{
    /**
     * Build the view model with config, serializer and URL builder.
     *
     * @param Config $config
     * @param Json $json
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly Config $config,
        private readonly Json $json,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Configuration consumed by the JS widget (data-mage-init).
     *
     * @return string
     */
    public function getJsonConfig(): string
    {
        return $this->json->serialize([
            'Logicera_CurrencyConverter/js/converter' => [
                'defaultFrom' => $this->config->getDefaultFrom() ?: 'USD',
                'defaultTo' => $this->config->getDefaultTo() ?: 'EUR',
                'endpoints' => [
                    'currencies' => $this->url->getUrl('currencyconverter/ajax/currencies'),
                    'rate' => $this->url->getUrl('currencyconverter/ajax/rate'),
                    'history' => $this->url->getUrl('currencyconverter/ajax/history'),
                ],
            ],
        ]);
    }
}
