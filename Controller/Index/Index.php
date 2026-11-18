<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Controller\Index;

use Logicera\CurrencyConverter\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Renders the storefront currency-converter page.
 */
class Index implements HttpGetActionInterface
{
    /**
     * Build the page controller with the page/forward factories and config.
     *
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param Config $config
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Config $config
    ) {
    }

    /**
     * Render the converter page, or forward to noroute when disabled.
     *
     * @return Page|Forward
     */
    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        return $this->pageFactory->create();
    }
}
