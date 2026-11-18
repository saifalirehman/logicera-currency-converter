<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Model;

use Logicera\CurrencyConverter\Api\FrankfurterServiceInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Frankfurter exchange-rate API client with response caching and rate limiting.
 */
class Frankfurter implements FrankfurterServiceInterface
{
    /**
     * Shared cache tag, so the whole integration can be flushed at once.
     */
    private const CACHE_TAG = 'logicera_currency_converter';

    /**
     * Build the API client with its HTTP, cache, logging and config collaborators.
     *
     * @param ClientInterface $httpClient
     * @param Json $json
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     * @param Config $config
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly Json $json,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCurrencies(): array
    {
        $currencies = $this->request('/currencies');
        // Order by display name so the dropdowns read naturally.
        asort($currencies, SORT_NATURAL | SORT_FLAG_CASE);

        return $currencies;
    }

    /**
     * @inheritDoc
     */
    public function getLatestRate(string $from, string $to): array
    {
        $from = $this->normaliseCode($from);
        $to = $this->normaliseCode($to);

        // Converting a currency to itself is always 1:1; the API omits that pair.
        if ($from === $to) {
            return ['base' => $from, 'target' => $to, 'rate' => 1.0, 'date' => date('Y-m-d')];
        }

        $response = $this->request('/latest', ['base' => $from, 'symbols' => $to]);
        if (!isset($response['rates'][$to])) {
            throw new LocalizedException(__('No exchange rate is available for %1 to %2.', $from, $to));
        }

        return [
            'base' => $from,
            'target' => $to,
            'rate' => (float)$response['rates'][$to],
            'date' => (string)($response['date'] ?? ''),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getHistory(string $from, string $to, string $startDate, string $endDate): array
    {
        $from = $this->normaliseCode($from);
        $to = $this->normaliseCode($to);
        $this->assertDate($startDate);
        $this->assertDate($endDate);

        if ($from === $to) {
            return $this->flatSeries($startDate, $endDate);
        }

        $response = $this->request($startDate . '..' . $endDate, ['base' => $from, 'symbols' => $to]);

        $series = [];
        foreach ($response['rates'] ?? [] as $date => $rates) {
            if (isset($rates[$to])) {
                $series[(string)$date] = (float)$rates[$to];
            }
        }
        ksort($series);

        return $series;
    }

    /**
     * GET a Frankfurter endpoint and return the decoded JSON body.
     *
     * Responses are cached by request URL: currency lists and daily rates change
     * at most once a day, so the API is not hit on every page load.
     *
     * @param string $path Endpoint path, with or without a leading slash.
     * @param array $query
     * @return array<mixed>
     * @throws LocalizedException
     */
    private function request(string $path, array $query = []): array
    {
        $url = $this->config->getApiBaseUrl() . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        // Reject non-HTTP(S) schemes such as file://. The host itself is not
        // allowlisted: the base URL is trusted admin-only config (see the SSRF
        // note in the README). Add a host allowlist if untrusted admins can set it.
        if (!preg_match('~^https?://~i', $url)) {
            throw new LocalizedException(__('The exchange-rate service URL must use http or https.'));
        }

        $cacheKey = 'logicera_cc_' . hash('sha256', $url);
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            return $this->json->unserialize($cached);
        }

        $data = $this->fetch($url);

        $this->cache->save(
            $this->json->serialize($data),
            $cacheKey,
            [self::CACHE_TAG],
            $this->config->getCacheLifetime()
        );

        return $data;
    }

    /**
     * Perform the HTTP call and decode the response.
     *
     * @param string $url
     * @return array<mixed>
     * @throws LocalizedException
     */
    private function fetch(string $url): array
    {
        $this->enforceRateLimit();

        try {
            $this->httpClient->setTimeout($this->config->getTimeout());
            $this->httpClient->get($url);

            if ($this->httpClient->getStatus() !== 200) {
                throw new LocalizedException(
                    __('The exchange-rate service returned HTTP %1.', $this->httpClient->getStatus())
                );
            }

            $data = $this->json->unserialize($this->httpClient->getBody());
            if (!is_array($data)) {
                throw new LocalizedException(__('Unexpected response from the exchange-rate service.'));
            }

            return $data;
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Frankfurter request failed: ' . $e->getMessage(), ['url' => $url]);
            throw new LocalizedException(__('Unable to reach the exchange-rate service. Please try again.'));
        }
    }

    /**
     * Cap outbound API calls to the configured number per minute.
     *
     * Caching already absorbs most traffic; this is a safety net against a burst
     * of cache-missing requests hammering the upstream service.
     *
     * @return void
     * @throws LocalizedException
     */
    private function enforceRateLimit(): void
    {
        $limit = $this->config->getRateLimit();
        if ($limit <= 0) {
            return;
        }

        $windowKey = 'logicera_cc_ratelimit_' . date('YmdHi');
        $count = (int)$this->cache->load($windowKey);
        if ($count >= $limit) {
            throw new LocalizedException(
                __('The exchange-rate service is busy right now. Please try again in a moment.')
            );
        }

        $this->cache->save((string)($count + 1), $windowKey, [self::CACHE_TAG], 60);
    }

    /**
     * Normalise a currency code to an upper-case ISO value.
     *
     * @param string $code
     * @return string
     */
    private function normaliseCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            throw new LocalizedException(__('Please provide a valid three-letter currency code.'));
        }

        return $code;
    }

    /**
     * Reject date strings that are not a plain Y-m-d, before they reach the API or date parser.
     *
     * @param string $date
     * @return void
     * @throws LocalizedException
     */
    private function assertDate(string $date): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new LocalizedException(__('Dates must be a valid Y-m-d value.'));
        }
    }

    /**
     * A flat 1:1 series for an identical currency pair, which the API does not return.
     *
     * @param string $startDate
     * @param string $endDate
     * @return array<string, float>
     */
    private function flatSeries(string $startDate, string $endDate): array
    {
        $series = [];
        $period = new \DatePeriod(
            new \DateTimeImmutable($startDate),
            new \DateInterval('P1D'),
            (new \DateTimeImmutable($endDate))->modify('+1 day')
        );
        foreach ($period as $day) {
            $series[$day->format('Y-m-d')] = 1.0;
        }

        return $series;
    }
}
