# Upgrade Guide

## 2.x → 3.0

### Requirements

- PHP 8.3 or higher (was 8.2)
- Laravel 12 or 13 (was 11–13)

### Breaking changes

Sending mail through the mailer (`MAIL_MAILER=microsoft-graph`) works without code changes, and every key of the mailer
configuration stays the same. You only have to act if your application:

- catches one of the package's exceptions, e.g. `MissingMailReadWritePermission` (all exceptions were renamed),
- registers the service provider manually instead of through package discovery,
- constructs, type-hints or extends the package's classes (the Graph client, the authentication classes, the mail
  API, the transport or the service provider).

**Reusable Graph client in `InnoGE\LaravelMsGraphMail\Graph`.** Token acquisition and the authenticated base
request moved out of `Services\MicrosoftGraphApiService` into `GraphClient`, built from typed `GraphCredentials`.
The mail API itself is now `InnoGE\LaravelMsGraphMail\MailApi`; it takes a `GraphClient`, and the protected
`getBaseRequest()`, `getAccessToken()` and `tokenCacheTtl()` methods are gone. Compose the client instead of extending
the mail service:

```php
use InnoGE\LaravelMsGraphMail\Graph\ClientSecret;
use InnoGE\LaravelMsGraphMail\Graph\GraphClient;
use InnoGE\LaravelMsGraphMail\Graph\GraphCredentials;

$graph = new GraphClient(new GraphCredentials($tenantId, $clientId, new ClientSecret($clientSecret)));

$graph->request()->get($graph->path('users', 'support@example.com', 'messages'))->throw();
```

`path()` URL-encodes every segment. `request()` refuses to send the token to any URL outside
`https://graph.microsoft.com/v1.0/` (`Graph\UntrustedGraphLinkException`), so persisted `@odata.nextLink` values can be followed
directly. Default `Prefer` preferences can be passed to the constructor, per-request ones to `request()`.

**Renamed and moved classes.** Exceptions follow Laravel's `…Exception` naming now.

| 2.x | 3.0 |
|---|---|
| `LaravelMsGraphMailServiceProvider` | `MsGraphMailServiceProvider` |
| `Services\MicrosoftGraphApiService` | `MailApi` |
| `Contracts\ClientAuthentication` | `Graph\ClientAuthentication` |
| `Authentication\ClientSecret` | `Graph\ClientSecret` (final) |
| `Authentication\ClientCertificate` | `Graph\ClientCertificate` (final) |
| `Exceptions\InvalidResponse` | `Graph\InvalidResponseException` |
| `Exceptions\ConfigurationMissing` | `Exceptions\MissingConfigurationException` |
| `Exceptions\ConfigurationInvalid` | `Exceptions\InvalidConfigurationException` |
| `Exceptions\MissingMailReadWritePermission` | `Exceptions\MissingMailReadWritePermissionException` |

The service provider is registered through package discovery, so only apps that list it manually in
`bootstrap/providers.php` have to update the class name. Its protected `clientAuthentication()` and
`requireConfigString()` helpers are private now; apps that extend the provider should build credentials themselves.

**The transport takes a `MailApi`.** `MicrosoftGraphTransport`'s first constructor argument is now
`MailApi $mailApi` (was `MicrosoftGraphApiService $microsoftGraphApiService`).

**Certificates are loaded when the mailer is created.** An unreadable certificate or key now throws
`Graph\InvalidCredentialsException` when the mailer is resolved, instead of on every token request. File paths must be
local files; stream wrappers such as `data://` or `http://` are rejected. Values containing a newline are always
treated as inline PEM.

**Exception messages.** `InvalidConfigurationException` now takes a reason instead of the invalid value, and no exception
message contains configured values anymore, so secrets cannot leak into logs. `InvalidResponseException` messages report
the type of an unexpected value instead of the value itself, and the class extends `UnexpectedValueException` (still an
`Exception`, so existing `catch (Exception $e)` blocks keep working). Credentials are `#[\SensitiveParameter]` and
redacted from stack traces.

**Credentials are validated on construction.** `new ClientSecret('')` and `GraphCredentials` with an empty tenant or
client id throw `Graph\InvalidCredentialsException`. The mailer configuration already rejected empty values before, so
this only affects code that builds the classes itself.

### Behavior corrections (no action needed, but worth knowing)

- Certificate authentication is selected only when `client_certificate.certificate` is filled. Previously a
  `client_certificate` array of empty env values selected certificate authentication for secret-only setups.
- Message ids and mailbox addresses are URL-encoded in Graph paths, so ids and addresses can no longer change the
  requested resource. `@` stays literal, so ordinary addresses produce exactly the same URLs as before.

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
work with `Mail.Send` alone. `save_to_sent_items` is honored on this path too: when disabled, the sent
message is deleted from Sent Items after sending (best effort); failed sends delete their draft.

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
