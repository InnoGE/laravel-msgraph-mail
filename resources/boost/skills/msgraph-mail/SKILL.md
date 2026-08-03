---
name: msgraph-mail
description: Configure, use, and troubleshoot the innoge/laravel-msgraph-mail package, which sends Laravel mail through the Microsoft Graph API instead of SMTP. Use when setting up the microsoft-graph mailer, registering the Azure app for it, configuring certificate authentication, or debugging Graph mail sending errors (401/403/404, AADSTS errors, timeouts).
license: MIT
metadata:
  author: innoge
---

# Microsoft Graph Mail

## When to use this skill

Use this skill when working with the `innoge/laravel-msgraph-mail` package (2.x):

- Configuring Laravel to send mail through Microsoft 365 / Microsoft Graph (replacing deprecated Office 365 SMTP).
- Creating or fixing the required Azure app registration, including certificate authentication.
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
        'from' => [ // optional: omit the key entirely to use the global mail.from
            'address' => env('MAIL_FROM_ADDRESS'),
            'name' => env('MAIL_FROM_NAME'),
        ],
        'save_to_sent_items' => env('MAIL_SAVE_TO_SENT_ITEMS', false), // optional
    ],
    // ...
],
```

Then activate it with `MAIL_MAILER=microsoft-graph` plus the `MICROSOFT_GRAPH_*` env vars.

`tenant_id` and `client_id` are always required. For authentication provide EITHER `client_secret` OR a `client_certificate` block (certificate wins when both are present):

```php
'client_certificate' => [
    'certificate' => env('MICROSOFT_GRAPH_CERTIFICATE'),   // PEM content or file path
    'private_key' => env('MICROSOFT_GRAPH_PRIVATE_KEY'),   // PEM content or file path
    'passphrase' => env('MICROSOFT_GRAPH_KEY_PASSPHRASE'), // optional
],
```

Certificate auth signs a JWT client assertion (requires ext-openssl); the certificate must be uploaded to the app registration under Certificates & secrets → Certificates. It avoids expiring client secrets.

The from address (mailer-level or global `mail.from`) must be the primary SMTP address of a real mailbox (licensed user or shared mailbox) in the tenant — Graph sends *as* that mailbox.

## Azure prerequisite

The package uses the OAuth2 client-credentials flow, so it needs an Entra ID (Azure AD) app registration with the Microsoft Graph **`Mail.Send` application permission** (not delegated) and **admin consent** granted. Sending mails larger than ~3 MB additionally requires **`Mail.ReadWrite`**.

Read `references/azure-app-registration.md` when the user needs to create the app registration, obtain the tenant/client ID or credentials, or fix permission/consent problems.

## How it works and behavior notes

- Sending is a `POST https://graph.microsoft.com/v1.0/users/{from-address}/sendMail`. Overriding the sender per mailable with `->from('other@tenant.com')` changes the URL target — that address must also be a real mailbox the app may send as.
- Mails work with standard `Mail::`, Mailables, and Notifications; no package-specific API.
- **Large mails** (total payload over ~3 MB): automatically sent via a draft message + chunked attachment upload sessions. Requires the `Mail.ReadWrite` application permission — otherwise a `MissingMailReadWritePermission` exception is thrown. Draft-based sends are *always* stored in Sent Items (Graph limitation).
- **`save_to_sent_items`** is read from the mailer's own config entry (works with mailers under any key). Per-mailable override: `$mailable->withSymfonyMessage(fn ($m) => $m->getHeaders()->add(new \Symfony\Component\Mailer\Header\MetadataHeader('save-to-sent-items', 'true')))`.
- The OAuth token is cached per tenant + client for its `expires_in` lifetime minus 60s. **Tokens do not pick up credential or permission changes until refreshed** — run `php artisan cache:clear` after rotating secrets/certificates or granting new Graph permissions.
- Attachment names use the real filename with extension where the framework exposes one; inline-image content ids are separate and stable.
- Only custom headers whose name starts with `X-` are forwarded to Graph (Symfony metadata/tag headers excluded); other custom headers are silently dropped. Recipient display names are dropped (only addresses are sent — Microsoft substitutes tenant-known names).
- The Graph API can be transiently slow or unreachable; queue mail with retries (`$tries`, backoff) rather than sending synchronously in requests.

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| `Configuration key tenant_id/client_id/client_secret/from.address for microsoft-graph mailer is missing` | Mailer entry missing from `config/mail.php` (common in apps upgraded from older Laravel versions), empty env vars, or stale config cache | Add the full mailer block above, set the env vars, run `php artisan config:clear` |
| `AADSTS7000215: Invalid client secret` | Wrong or expired client secret, or the secret's **ID** was copied instead of its **Value** | Create a new secret, copy the Value, update env; check secret expiry — or switch to certificate auth |
| `AADSTS700016: Application ... was not found` | Wrong `client_id` or app registered in a different tenant | Verify Application (client) ID and Directory (tenant) ID on the app's Overview page |
| `AADSTS90002: Tenant ... not found` | Wrong `tenant_id` | Use the Directory (tenant) ID GUID |
| `AADSTS700027: Client assertion contains an invalid signature` | Certificate not uploaded to the app registration, or wrong certificate/private key pair | Upload the certificate under Certificates & secrets → Certificates; verify thumbprints match |
| `403 ErrorAccessDenied` | `Mail.Send` application permission missing, admin consent not granted, or an Exchange ApplicationAccessPolicy blocks this mailbox | Add `Mail.Send` as an **application** permission and grant admin consent; check access policies |
| `MissingMailReadWritePermission` exception | Mail exceeds ~3 MB and the app lacks `Mail.ReadWrite` | Grant `Mail.ReadWrite` application permission + admin consent, then `php artisan cache:clear` (cached tokens lack new permissions) — or reduce attachment size |
| Permission was granted but errors persist | The cached token was issued before the grant and does not contain the new role | `php artisan cache:clear` |
| `404 ErrorInvalidUser: The requested user '...' is invalid` | The from address is not an existing mailbox, or is an alias instead of the primary SMTP address | Use the mailbox's primary SMTP address; verify the mailbox exists in the tenant |
| Auth keeps failing after rotating the client secret | Old token still cached | `php artisan cache:clear` |
| `ErrorMessageSizeExceeded` | Attachments exceed Graph's hard 150 MB upload-session limit (mails up to ~150 MB are handled automatically) | Send download links instead of attachments |
| `cURL error 28` / connection timeouts | Transient Graph API or DNS issues | Queue mails with retries and backoff; don't raise timeouts expecting a fix |
| Mail arrives but is not in Sent Items | `save_to_sent_items` is false (default) | Enable it in the mailer config or per mailable via the metadata header |

For live debugging, use Boost's MCP tools where available: `last-error` / `read-log-entries` to see the actual Graph error response, and `search-docs` for Laravel mail documentation.

## Version note

Package 1.x behaves differently in three relevant ways: `save_to_sent_items` only works on the mailer key literally named `microsoft-graph`, large mails fail with `ErrorMessageSizeExceeded` (~4 MB cap, no upload sessions), and there is no certificate auth. See `UPGRADE.md` in the package for the full 1.x → 2.0 migration guide.
