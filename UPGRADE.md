# Upgrade Guide

## 1.x → 2.0

### Requirements

- PHP 8.2 or higher (was 8.1)
- Laravel 11, 12, or 13 (was 9.38–13)
- symfony/mailer 7 or 8 (was 6–8)

### Breaking changes

**`access_token_ttl` removed.** The access token is now cached for the lifetime reported by Microsoft
(`expires_in`) minus a 60 second safety buffer. Remove the key from your mailer config if you set it —
it now throws no error but has no effect either.

**Token cache key changed.** Tokens are now cached per tenant *and* client (previously per tenant only,
which made two apps in the same tenant share one token). The first send after upgrading simply acquires
a fresh token; no action needed.

**Attachment names.** Attachment `name` values now use the real filename including its extension when
the framework exposes one (fixes inline image rendering in clients like Thunderbird). The `contentId`
used for inline images is unchanged. If you post-process sent payloads by attachment name, review that
logic.

**Large mails need `Mail.ReadWrite` (only if you send them).** Mails above ~3 MB total are now sent
automatically via a draft + upload sessions instead of failing with `ErrorMessageSizeExceeded`. This
path requires the `Mail.ReadWrite` application permission with admin consent. Small mails continue to
work with `Mail.Send` alone. Note: draft-based sends are always stored in Sent Items regardless of
`save_to_sent_items`.

**Constructor signatures changed** (only relevant if you construct or extend the classes yourself —
normal mailer usage is unaffected): `MicrosoftGraphApiService` now takes a `ClientAuthentication`
implementation instead of `clientSecret`/`accessTokenTtl`, and `MicrosoftGraphTransport` gained a
`saveToSentItems` constructor parameter.

### Behavior corrections (no action needed, but worth knowing)

- `save_to_sent_items` is now read from the mailer's own config entry. Mailers registered under a custom
  key (not `microsoft-graph`) previously *silently ignored* the option and sent `saveToSentItems: false`.
- The mailer-level `from` is now optional; when the key is omitted entirely, Laravel's global `mail.from`
  is used. A present-but-empty `from` still fails fast with `ConfigurationMissing`.
- Custom `X-Metadata-*` / `X-Tag-*` headers (Symfony transport instructions) are no longer forwarded to
  recipients as literal mail headers.

### New features

- **Certificate authentication**: configure `client_certificate` (PEM content or file paths) instead of
  `client_secret`. See the README.
- **Per-mailable `saveToSentItems` override** via `new MetadataHeader('save-to-sent-items', 'true'|'false')`.
- **Automatic large-attachment handling** via Graph upload sessions (see above).

### After upgrading

Run `php artisan config:clear && php artisan cache:clear` once. Cached tokens do not carry newly granted
permissions (e.g. `Mail.ReadWrite`), so clear the cache after any permission change too.
