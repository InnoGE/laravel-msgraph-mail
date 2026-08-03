# Contributing

Contributions are welcome and will be fully credited.

## Pull Requests

- **Target the right branch** — `2.x` features and fixes go against `main`; security or compatibility fixes for the 1.x line go against `1.x`.
- **Add tests** — your change should come with Pest tests that pin its behavior. Run the suite with `composer test`.
- **Keep the analysers happy** — `composer analyse` (PHPStan, level max, empty baseline) and `vendor/bin/pint` must pass.
- **One feature per PR** — smaller pull requests are reviewed and merged faster.
- **Document behavior** — update `README.md` (and `UPGRADE.md` for breaking changes) when your change affects how the package is used.

## Reporting Issues

Please use the issue templates and include the package, PHP, and Laravel versions plus the exact error output. For anything security-related, follow the [security policy](../../security/policy) instead of opening a public issue.
