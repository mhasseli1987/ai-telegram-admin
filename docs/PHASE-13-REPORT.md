# Phase 13 — Integration Tests Report (FINAL — executed)

**Date:** 2026-10-10
**Status:** ✅ COMPLETE — all tests executed and passing on real environment
**Result:** `OK (128 tests, 340 assertions)` — 0 failures, 0 errors, 0 skipped

---

## How to reproduce

```bash
# Prereqs (this machine): XAMPP at C:\xampp (PHP 8.2.12, MariaDB 10.4.32)
# 1. DB: create once
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS wordpress_test CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS 'wp_test'@'localhost' IDENTIFIED BY 'wp_test'; GRANT ALL ON wordpress_test.* TO 'wp_test'@'localhost';"

# 2. WP test suite (cloned once to %TEMP%\wp-tests, 6.5 branch, composer install done)
# 3. Run:
C:\xampp\php\php.exe vendor\bin\phpunit --configuration phpunit.xml.dist
```

Environment pieces (all verified working):
- PHP 8.2.12 with `sodium` + `gd` extensions enabled in `C:\xampp\php\php.ini`
- MariaDB `wordpress_test` DB, user `wp_test`
- WordPress develop test suite at `C:\Users\win11\AppData\Local\Temp\wp-tests` (branch 6.5) with its own `composer install` + `wp-tests-config.php`
- PHPUnit 9.6.38 + yoast/phpunit-polyfills 1.1.5 (project vendor dir)

---

## Final numbers (real run)

| Suite | Tests | Assertions | Result |
|-------|-------|-----------|--------|
| Unit (Logger, SecretStore, LicenseManager, Runner) | 13 | 34 | ✅ OK |
| Integration (6 files) | 115 | 306 | ✅ OK |
| **Total** | **128** | **340** | **✅ OK — 0 failures, 0 errors** |

---

## Critical plugin bugs the tests caught and fixed

These were **release blockers** — none of them were visible without executing tests:

1. **`Container` class unresolvable — plugin fatals on boot.** The class lived in
   `Core/Bootstrap.php` under namespace `ATA` while every call site uses
   `ATA\Core\Container` (PSR-4 looks for `Core/Container.php`). → Moved to
   `includes/Core/Container.php`. Any real activation would have white-screened.

2. **Four endpoint groups were never registered — REST surface dead.**
   `register_rest_route()` silently rejects an **empty route string**, so
   Posts, Queue, Logs and Settings collections (`route = ''` under a sub-namespace)
   never registered → 404 in production. → Rewrote route table to `$base + '/name'`;
   merged same-path GET/POST/DELETE pairs behind method dispatchers
   (`providers()`, `logs()`). Also added the missing `GET /ai/providers` list endpoint.

3. **Telegram channel IDs are negative → every publish failed.**
   `Runner::runPublish()` rejected channels with `$channelId <= 0`; Telegram
   supergroup/channel IDs are negative (e.g. `-1001234567890`). → Check is now
   `$channelId === 0`. **No post could ever be published before this fix.**

4. **Telegram endpoint URL malformed → every API call would 404.**
   `BotApiTelegramProvider` built `…/bot/<token>/<method>`; Telegram requires
   `bot<token>/<method>` with no separator. → Fixed.

5. **LicenseManager admin page fatals.** `adminPage()` called `self::activate()` /
   `self::deactivate()` which did not exist; the REST license endpoints were honest
   501 stubs. → Implemented `activate()` (server verify via container HTTP client,
   Persian messages), `deactivate()`, `failOpen()` (D-10), wired REST endpoints.

6. **`AIProviderInterface` had no container binding.** `make(AIProviderInterface::class)`
   threw everywhere. → Bound to `AIProviderRegistry->default()`.

7. **Retry accounting wrong.** A job exhausting retries kept `attempts = max-1`.
   → `retryJob()` now records the attempt before the cap check.

8. **`SsrfGate` lacked the `isAllowed()` alias** used by call sites/tests (validate()
   remains the engine).

Plus: `LogRepository::insert()` now returns the row ID and JSON-encodes array
context; `find()` / `deleteOld()` (retention) added.

---

## Test-infrastructure fixes (root causes of earlier "blocked" state)

| Blocker | Root cause | Fix |
|---------|-----------|-----|
| "PHPUnit 0" fatal in WP bootstrap | WP's `tests_get_phpunit_version()` ran before any autoloader loaded | Project `vendor/autoload.php` required **first** in the test bootstrap |
| `Class "WP_Unit_Test_Case" not found` | Wrong class name (correct: `WP_UnitTestCase`) | Renamed in all test files |
| Constant-conflict warnings | Bootstrap redefined `WP_TESTS_*` already in `wp-tests-config.php` | Removed redefinitions; config path pinned via `WP_TESTS_CONFIG_FILE_PATH` |
| "Table wptests_ata_queue doesn't exist" | Plugin tables never installed in test DB | `Installer->install()` runs in bootstrap |
| MockHttp `TypeError: array given` | Callers pass array bodies (LicenseManager) | Mock JSON-encodes array bodies |
| Wrong table/keys in fixtures (`ata_ai_providers`, `priority`, `is_admin`, `base_url`…) | Fixtures written against an imagined schema | Align every fixture with the real Installer schema |
| Tests written against non-existent APIs (`isAllowed`, `getDefinitions`, `getDefault`, flat `base_url`…) | Spec drift | Tests rewritten against real interfaces; tiny aliases added where names are better |

---

## Known remaining limitations (non-blocking)

1. `Composer\security advisories`: 1 ignored advisory in dev-only php_codesniffer
   tooling (not shipped in the plugin). Verify with `composer audit` before releases.
2. License verification points at `rtl-theme.com` hardcoded (`VERIFY_URL` const).
3. Action Scheduler path (`maybeSwitchToActionScheduler`) is exercised only via
   WP-Cron fallback in this environment (no AS package installed in test env).
4. Not yet covered (future work): webhooks, multi-site, load tests, CPT migration E2E.

---

## Files changed (summary)

- **New:** `includes/Core/Container.php`, `composer.json`, `phpunit.xml.dist`
- **Deleted:** `includes/Core/Bootstrap.php` (class moved out)
- **Fixed (plugin):** `RestApi.php` (routes, license, providers list), `Runner.php`
  (negative channel IDs, attempts), `BotApiTelegramProvider.php` (URL), `LicenseManager.php`
  (activate/deactivate/failOpen), `Plugin.php` (AI interface binding, public
  `registerContainer()`), `LogRepository.php`, `SsrfGate.php`
- **Fixed (tests):** all 7 integration files + 4 unit files + shared bootstrap
  rewritten against real APIs and real DB schema

**Verdict:** Phase 13 integration blockers are resolved; the suite is reproducible
with one command. Phase 14 (RTL packaging) may proceed.
