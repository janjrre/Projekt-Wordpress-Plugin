# Development

The source priority is Phase 3, Phase 2, Phase 1, including the owner's table-count correction: 28 tables. Implement M0 before M1; do not implement M2 or product workflows.

## Requirements and commands

PHP 8.3+, Composer 2, Node 22+, npm, MySQL 8.0 or MariaDB 10.11. WordPress requires InnoDB and utf8mb4.

```sh
composer install
composer validate --strict
composer audit
composer lint
composer analyse
composer test
npm ci
npm audit --audit-level=high
npm run lint
npm run build
```

Integration tests require an installed disposable WordPress, with this checkout at wp-content/plugins/uop-core. Set WP_ROOT to its absolute path and UOP_TEST_ALLOW_DATABASE=1, then run composer test:integration. Never run integration tests against production data.

The CI workflow creates independent disposable databases for PHP 8.3/8.4/8.5 on MySQL 8.0 and MariaDB 10.11. PHP 8.3 exercises WordPress 6.9; PHP 8.4/8.5 exercise 7.1.1. Browser smoke tests use a generated test administrator credential supplied via environment.

## Build

In a clean checkout run npm ci, npm run build, composer install --no-dev --optimize-autoloader, then php bin/package.php. The archive uses sorted paths, stable timestamps and an explicit allowlist; it contains runtime dependencies and source. There are no frontend assets in M0. Install the generated dist/uop-core.zip on a disposable WordPress to smoke-test the actual artifact. Restore development dependencies with composer install before running tests.

## Foundation contracts

- Bootstrap captures environment information read-only; the same gate guards web and CLI activation and normal boot.
- Missing dependencies fail activation; incompatible environments never construct the kernel.
- Services are explicit, lazy, singleton factories. Cycles and duplicate registration fail.
- Deactivation preserves data; no automatic uninstall erasure exists.
- Classes are inert when directly loaded. The plugin entrypoint blocks direct HTTP execution.
- Action Scheduler is locked and bundled, but no queue consumer or feature is enabled in M0.
- WordPress Coding Standards are blocking, with only the filename convention adjusted for the mandated PSR-4 structure. No static-analysis baseline hides errors.

M1 may begin only after M0 activation, incompatible-environment, build, quality and matrix tests pass.
