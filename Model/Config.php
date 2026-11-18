<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed accessor for the module's store configuration.
 */
class Config
{
    private const XML_PATH_ENABLED = 'currency_converter/general/enabled';
    private const XML_PATH_DEFAULT_FROM = 'currency_converter/general/default_from';
    private const XML_PATH_DEFAULT_TO = 'currency_converter/general/default_to';
    private const XML_PATH_HISTORY_DAYS = 'currency_converter/general/history_days';
    private const XML_PATH_BASE_URL = 'currency_converter/api/base_url';
    private const XML_PATH_TIMEOUT = 'currency_converter/api/timeout';
    private const XML_PATH_CACHE_LIFETIME = 'currency_converter/api/cache_lifetime';
    private const XML_PATH_RATE_LIMIT = 'currency_converter/api/rate_limit';

    /**
     * Build the configuration accessor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether the storefront converter page is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Default base ("from") currency code.
     *
     * @return string
     */
    public function getDefaultFrom(): string
    {
        return strtoupper((string)$this->getValue(self::XML_PATH_DEFAULT_FROM));
    }

    /**
     * Default target ("to") currency code.
     *
     * @return string
     */
    public function getDefaultTo(): string
    {
        return strtoupper((string)$this->getValue(self::XML_PATH_DEFAULT_TO));
    }

    /**
     * Number of days of history shown on the chart.
     *
     * @return int
     */
    public function getHistoryDays(): int
    {
        // Clamp to a sane window: at least a day, at most roughly a year.
        return min(365, max(1, (int)$this->getValue(self::XML_PATH_HISTORY_DAYS)));
    }

    /**
     * Frankfurter API base URL, without a trailing slash.
     *
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        return rtrim((string)$this->getValue(self::XML_PATH_BASE_URL), '/');
    }

    /**
     * HTTP request timeout, in seconds.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_TIMEOUT));
    }

    /**
     * How long API responses are cached, in seconds.
     *
     * @return int
     */
    public function getCacheLifetime(): int
    {
        return max(0, (int)$this->getValue(self::XML_PATH_CACHE_LIFETIME));
    }

    /**
     * Maximum outbound API calls per minute (0 disables the cap).
     *
     * @return int
     */
    public function getRateLimit(): int
    {
        return max(0, (int)$this->getValue(self::XML_PATH_RATE_LIMIT));
    }

    /**
     * Read a store-scoped configuration value.
     *
     * @param string $path
     * @return mixed
     */
    private function getValue(string $path): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }
}
