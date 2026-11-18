<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Controller\Ajax;

use Logicera\CurrencyConverter\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Shared behaviour for the JSON endpoints: the enabled-check, a consistent
 * response envelope, and one place that turns exceptions into clean, translated
 * error payloads.
 */
abstract class AbstractAjax implements HttpGetActionInterface
{
    /**
     * Build the base controller with the JSON factory, logger and config.
     *
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Config $config
     */
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly LoggerInterface $logger,
        protected readonly Config $config
    ) {
    }

    /**
     * Dispatch the request and wrap the result (or any error) as JSON.
     *
     * @return Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        // The whole integration honours the same switch as the storefront page.
        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(404)
                ->setData(['success' => false, 'message' => (string)__('This feature is not available.')]);
        }

        try {
            return $result->setData(['success' => true] + $this->handle());
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(422)
                ->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('Currency converter endpoint failed: ' . $e->getMessage());
            return $result->setHttpResponseCode(500)
                ->setData([
                    'success' => false,
                    'message' => (string)__('Something went wrong. Please try again.'),
                ]);
        }
    }

    /**
     * Produce the endpoint-specific payload (merged under the "success" flag).
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    abstract protected function handle(): array;
}
