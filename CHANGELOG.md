# Changelog

All notable changes to `laravel-msgraph-mail` will be documented in this file.

## 2.0.0 - Unreleased

See [UPGRADE.md](UPGRADE.md) for the full migration guide.

### Added

- Certificate-based client authentication via `client_certificate` (JWT client assertion, PEM content or file paths, optional passphrase) — no more expiring client secrets
- Automatic handling of mails above the ~4 MB Graph `sendMail` limit through draft messages and chunked attachment upload sessions (requires the `Mail.ReadWrite` application permission); closes #41, supersedes #42
- Per-mailable `saveToSentItems` override via Symfony `MetadataHeader('save-to-sent-items', …)`; supersedes #50
- Mailer-level `from` is now optional and falls back to the global `mail.from`; supersedes #35 and #44
- Laravel Boost skill (`msgraph-mail`) with configuration guidance, troubleshooting, and an Azure app registration walkthrough

### Changed

- Requires PHP >= 8.2 and Laravel 11, 12, or 13
- Access tokens are cached for their actual `expires_in` lifetime (minus a safety buffer) and per tenant *and* client; the undocumented `access_token_ttl` option was removed
- Attachment names use the real filename including its extension where the framework exposes one (inline images now render in clients like Thunderbird); closes #69 and the #58/#52 lineage
- `save_to_sent_items` is read from the mailer's own config entry, fixing silently-disabled saving on mailers registered under custom keys
- With `save_to_sent_items` disabled, large (draft-based) sends permanently delete the sent copy; failed draft sends clean up the orphaned draft
- Symfony metadata/tag headers are no longer forwarded to recipients as literal mail headers
- PHPStan baseline emptied; all previously suppressed issues fixed with real types

## 1.0.0 - 2023-02-13

Initial Release 🍾

**Full Changelog**: https://github.com/InnoGE/laravel-msgraph-mail/commits/1.0.0
