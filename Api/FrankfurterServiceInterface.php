<?php
declare(strict_types=1);

namespace Logicera\CurrencyConverter\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Thin wrapper around the Frankfurter exchange-rate API (https://frankfurter.dev).
 *
 * Declared as a service contract so controllers depend on the interface rather
 * than the concrete HTTP client, which keeps the integration swappable and testable.
 */
interface FrankfurterServiceInterface
{
    /**
     * List every currency the API supports, as an ISO code => display name map.
     *
     * @return array<string, string>
     * @throws LocalizedException
     */
    public function getCurrencies(): array;

    /**
     * Latest rate for one unit of $from expressed in $to.
     *
     * @param string $from ISO 4217 code, e.g. "USD"
     * @param string $to   ISO 4217 code, e.g. "EUR"
     * @return array{base: string, target: string, rate: float, date: string}
     * @throws LocalizedException
     */
    public function getLatestRate(string $from, string $to): array;

    /**
     * Daily $from->$to rates between two dates (inclusive), oldest first.
     *
     * @param string $from      ISO 4217 code
     * @param string $to        ISO 4217 code
     * @param string $startDate Y-m-d
     * @param string $endDate   Y-m-d
     * @return array<string, float> date (Y-m-d) => rate
     * @throws LocalizedException
     */
    public function getHistory(string $from, string $to, string $startDate, string $endDate): array;
}
