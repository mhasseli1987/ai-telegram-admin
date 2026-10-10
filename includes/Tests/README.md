# ATA Unit Tests

This directory contains PHPUnit-compatible unit tests for the ATA plugin.

## Test Files
- `TestCase.php` — Base test case class
- `TestLogger.php` — Tests Logger redaction (secret key masking)
- `TestSecretStore.php` — Tests SecretStore encryption/decryption via public API
- `TestLicenseManager.php` — Tests LicenseManager activate/deactivate/fail-open
- `TestRunner.php` — Tests Cron Runner hooks and basic structure

## Running Tests
These tests are designed for the WordPress PHPUnit environment. To run:
1. Ensure WP_TEST_DIR is set
2. Use `wp phpunit --path=` or your preferred PHPUnit runner
3. Tests exercise core contracts: logging redaction, secret encryption, license logic

## Design
- All tests use the `WP_Unit_Test_Case` base (included in WP testing library)
- Tests are isolated: setUp/tearDown clean DB state
- Focus on contract compliance: logging redaction patterns, encryption roundtrips, license status logic