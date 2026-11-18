<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Controller\Ajax;

use Logicera\CurrencyConverter\Api\FrankfurterServiceInterface;
use Logicera\CurrencyConverter\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Returns the latest rate for a From/To pair.
 */
class Rate extends AbstractAjax
{
    /**
     * Build the endpoint with the base dependencies, exchange service and request.
     *
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Config $config
     * @param FrankfurterServiceInterface $service
     * @param RequestInterface $request
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Config $config,
        private readonly FrankfurterServiceInterface $service,
        private readonly RequestInterface $request
    ) {
        parent::__construct($resultJsonFactory, $logger, $config);
    }

    /**
     * @inheritDoc
     */
    protected function handle(): array
    {
        $from = (string)$this->request->getParam('from', '');
        $to = (string)$this->request->getParam('to', '');
        if ($from === '' || $to === '') {
            throw new LocalizedException(__('Please choose both currencies.'));
        }

        return ['rate' => $this->service->getLatestRate($from, $to)];
    }
}
