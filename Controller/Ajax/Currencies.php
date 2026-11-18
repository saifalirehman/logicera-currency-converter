<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Controller\Ajax;

use Logicera\CurrencyConverter\Api\FrankfurterServiceInterface;
use Logicera\CurrencyConverter\Model\Config;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

/**
 * Returns the list of supported currencies for the dropdowns.
 */
class Currencies extends AbstractAjax
{
    /**
     * Build the endpoint with the base dependencies and the exchange service.
     *
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Config $config
     * @param FrankfurterServiceInterface $service
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Config $config,
        private readonly FrankfurterServiceInterface $service
    ) {
        parent::__construct($resultJsonFactory, $logger, $config);
    }

    /**
     * @inheritDoc
     */
    protected function handle(): array
    {
        return ['currencies' => $this->service->getCurrencies()];
    }
}
