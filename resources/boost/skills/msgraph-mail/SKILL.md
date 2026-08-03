---
name: msgraph-mail
description: Configure, use, and troubleshoot the innoge/laravel-msgraph-mail package, which sends Laravel mail through the Microsoft Graph API instead of SMTP. Use when setting up the microsoft-graph mailer, registering the Azure app for it, or debugging Graph mail sending errors (401/403/404/413, AADSTS errors, timeouts).
license: MIT
metadata:
  author: innoge
---

# Microsoft Graph Mail

## When to use this skill

Use this skill when working with the `innoge/laravel-msgraph-mail` package:

- Configuring Laravel to send mail through Microsoft 365 / Microsoft Graph (replacing deprecated Office 365 SMTP).
- Creating or fixing the required Azure app registration.
- Debugging failed sends through the `microsoft-graph` mailer.

## Configuration

The package registers a `microsoft-graph` mail transport via package auto-discovery. There is no publishable package config — all configuration lives in `config/mail.php` under `mailers`:

```php
'mailers' => [
    'microsoft-graph' => [
        'transport' => 'microsoft-graph',
        'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_GRAPH_CLIENT_SECRET'),
        'tenant_id' => env('MICROSOFT_GRAPH_TENANT_ID'),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS'),
            'name' => env('MAIL_FROM_NAME'),
        ],
        'save_to_sent_items' => env('MAIL_SAVE_TO_SENT_ITEMS', false), // optional
        'access_token_ttl' => 3000, // optional, seconds, must be an int if set
    ],
    // ...
],
```

Then activate it:

```env
MAIL_MAILER=microsoft-graph
MICROSOFT_GRAPH_TENANT_ID=...
MICROSOFT_GRAPH_CLIENT_ID=...
MICROSOFT_GRAPH_CLIENT_SECRET=...
MAIL_FROM_ADDRESS=sender@yourtenant.com
```

`tenant_id`, `client_id`, `client_secret`, and `from.address` are required and must be non-empty strings — the transport throws `ConfigurationMissing` / `ConfigurationInvalid` at resolve time otherwise.

`MAIL_FROM_ADDRESS` must be a real, existing mailbox (licensed user or shared mailbox) in the tenant — Graph sends *as* that mailbox.

## Azure prerequisite

The package uses the OAuth2 client-credentials flow, so it needs an Entra ID (Azure AD) app registration with the Microsoft Graph **`Mail.Send` application permission** (not delegated) and **admin consent** granted.

Read `references/azure-app-registration.md` when the user needs to create the app registration, obtain the tenant/client ID or client secret, or fix permission/consent problems.

## How it works and behavior notes

- Sending is a `POST https://graph.microsoft.com/v1.0/users/{from-address}/sendMail`. Overriding the sender per mailable with `->from('other@tenant.com')` changes the URL target — that address must also be a real mailbox the app may send as.
- Mails work with standard `Mail::`, Mailables, and Notifications; no package-specific API.
- The OAuth token is cached under the cache key `microsoft-graph-api-access-token-{tenant_id}` for `access_token_ttl` seconds (default 3000). After rotating the client secret, sends can keep failing until `php artisan cache:clear`.
- Attachments are base64-encoded into a single JSON request. Microsoft Graph caps `sendMail` payloads at ~4 MB (≈3 MB of raw attachments). The package has no upload-session fallback, so larger mails fail with `ErrorMessageSizeExceeded` — send links to files instead of large attachments.
- Only custom headers whose name starts with `X-` are forwarded to Graph; other custom headers are silently dropped. Recipient display names are also dropped (only the address is sent).
- `save_to_sent_items` is read at send time from the literal config key `mail.mailers.microsoft-graph.save_to_sent_items`. If the mailer is registered under a different key name, it silently sends with `saveToSentItems: false`.
- The Graph API can be transiently slow or unreachable; queue mail and configure retries (`$tries`, backoff) rather than sending synchronously in requests.

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| `Configuration key tenant_id/client_id/client_secret/from.address for microsoft-graph mailer is missing` | Mailer entry missing from `config/mail.php` (common in apps upgraded from older Laravel versions), empty env vars, or stale config cache | Add the full mailer block above, set the env vars, run `php artisan config:clear` |
| `AADSTS7000215: Invalid client secret` | Wrong or expired client secret, or the secret's **ID** was copied instead of its **Value** | Create a new secret, copy the Value, update env; check secret expiry |
| `AADSTS700016: Application ... was not found` | Wrong `client_id` or app registered in a different tenant | Verify Application (client) ID and Directory (tenant) ID on the app's Overview page |
| `AADSTS90002: Tenant ... not found` | Wrong `tenant_id` | Use the Directory (tenant) ID GUID |
| `403 ErrorAccessDenied` | `Mail.Send` application permission missing, admin consent not granted, or an Exchange ApplicationAccessPolicy blocks this mailbox | Add `Mail.Send` as an **application** permission and grant admin consent; check access policies |
| `404 ErrorInvalidUser: The requested user '...' is invalid` | The from address is not an existing mailbox, or is an alias instead of the primary SMTP address | Use the mailbox's primary SMTP address; verify the mailbox exists in the tenant |
| `ErrorMessageSizeExceeded` / HTTP 413 | Total attachments exceed the ~3–4 MB Graph limit | Reduce attachment size or send download links |
| Auth keeps failing after rotating the client secret | Old token still cached | `php artisan cache:clear` (or wait out `access_token_ttl`) |
| `cURL error 28` / connection timeouts | Transient Graph API or DNS issues | Queue mails with retries and backoff; don't raise timeouts expecting a fix |
| Inline/embedded images not displayed | Old package version (Laravel 12 inline attachment bug, fixed in 1.5) | `composer update innoge/laravel-msgraph-mail` |

For live debugging, use Boost's MCP tools where available: `last-error` / `read-log-entries` to see the actual Graph error response, and `search-docs` for Laravel mail documentation.
