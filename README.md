# Logicera_CurrencyConverter

A Magento 2 module that adds a storefront **currency exchange converter** page. A
buyer picks a *From* and *To* currency, sees the current rate, views how the rate
has moved over a configurable period, and can swap the two currencies with one click.

Exchange data comes from the free [Frankfurter API](https://frankfurter.dev)
(European Central Bank reference rates, no API key required).

## Requirements

- Magento 2.4.x
- PHP 8.1 – 8.4

## Installation

```bash
# From the Magento root
mkdir -p app/code/Logicera
cp -r /path/to/this-repo app/code/Logicera/CurrencyConverter

bin/magento module:enable Logicera_CurrencyConverter
bin/magento setup:upgrade
bin/magento cache:flush
```

The page is then available at `/currencyconverter` on the storefront.

## Configuration

**Stores → Configuration → Logicera → Currency Converter**

| Setting | Default | Notes |
|---------|---------|-------|
| Enable Converter Page | Yes | Turns the `/currencyconverter` page and its AJAX endpoints on or off |
| Default From / To Currency | USD / EUR | Pre-selected on page load |
| History Period (days) | 30 | Range shown on the chart |
| API Base URL | `https://api.frankfurter.dev/v1` | Swappable (e.g. a self-hosted Frankfurter) |
| Request Timeout | 10s | Upstream HTTP timeout |
| Cache Lifetime | 3600s | How long API responses are cached |
| Rate Limit | 60/min | Hard cap on outbound API calls; `0` disables it |

## How it works

```
Browser ──► Controller\Index\Index ──► converter.phtml ──► js/converter.js
                                                                │ (AJAX, JSON)
                                                                ▼
                            Controller\Ajax\{Currencies,Rate,History}
                                                                │
                                                                ▼
                        Api\FrankfurterServiceInterface ──► Model\Frankfurter
                                                                │
                                              cache ──► Frankfurter API (HTTP)
```

- **Service contract** – controllers depend on `FrankfurterServiceInterface`, not
  the concrete client, so the integration is swappable and unit-testable.
- **API access is server-side.** The browser only ever talks to this module's own
  endpoints; the upstream URL (and any credentials, were a different provider used)
  is never exposed to the client.
- **Caching + rate limiting.** Responses are cached by request URL (rates change at
  most once a day); a per-minute cap protects the upstream API from bursts.
- **Chart.js** renders the historical line chart, loaded via RequireJS.

## Endpoints

| URL | Returns |
|-----|---------|
| `/currencyconverter` | The converter page |
| `/currencyconverter/ajax/currencies` | `{ success, currencies: { CODE: name } }` |
| `/currencyconverter/ajax/rate?from=USD&to=EUR` | `{ success, rate: { base, target, rate, date } }` |
| `/currencyconverter/ajax/history?from=USD&to=EUR` | `{ success, labels: [], values: [] }` |

## Quality: tests, static analysis & CI

Three checks run on every push / pull request via GitHub Actions
(`.github/workflows/ci.yml`), all required to merge:

| Check | What it runs |
|-------|--------------|
| **Static tests** | `phpcs` (Magento2 ruleset) · PHPStan · PHPMD · Gitleaks (secret scan) · Rector (`--dry-run`, enforces PHP 8.1 idioms) |
| **Unit tests** | PHPUnit (`Test/Unit`) |
| **Integration tests** | Magento integration framework (`Test/Integration`) |

A lightweight **AI code review** (`dev/tests/review/ai_review.py`, via OpenRouter) is
also included as a **local** tool — run it over a diff before pushing:

```bash
OPENROUTER_API_KEY=... python3 dev/tests/review/ai_review.py
```

Run the checks locally:

```bash
# Coding standard
vendor/bin/phpcs --standard=Magento2 app/code/Logicera/CurrencyConverter

# Unit tests
vendor/bin/phpunit -c app/code/Logicera/CurrencyConverter/Test/Unit

# Integration tests (from dev/tests/integration)
../../vendor/bin/phpunit --testsuite "Logicera_CurrencyConverter"
```

## Design notes & trade-offs

- **ViewModel over a block.** Template data comes from a `ViewModel` (`ArgumentInterface`),
  not a custom block — lighter and the preferred modern pattern. No exchange data is
  fetched at render time; the page pulls it from this module's own AJAX endpoints.
- **Rate limiter.** The per-minute cap uses a cache counter, which is intentionally
  simple and not atomic. For strict enforcement under high concurrency, back it with an
  atomic store (e.g. Redis `INCR`). Caching already absorbs the vast majority of traffic.
- **Chart.js via CDN.** Loaded through RequireJS from a pinned CDN to keep the repo light,
  and the CDN host is whitelisted for Content-Security-Policy in `etc/csp_whitelist.xml`.
  For air-gapped deployments, vendor the UMD build under `view/frontend/web/js/lib/` and
  point `requirejs-config.js` at it.
- **Modern PHP, enforced.** Constructor property promotion with `readonly` throughout
  (justifies the PHP 8.1 floor); a Rector `--dry-run` job keeps it that way. Strings are
  translatable via `i18n/en_US.csv`.
- **Read-only GET endpoints.** The AJAX endpoints return public exchange data and take no
  action, so they use `HttpGetActionInterface` without a form key; input is validated
  (strict ISO code + real calendar date) before any outbound call, and honours the enable toggle.
- **Admin-configurable API URL.** The base URL is a trusted-admin setting (like a payment
  gateway endpoint); it is validated to `http(s)` but not restricted to a host allowlist. If
  untrusted admins can reach the config, add a hostname allowlist to close off SSRF.
