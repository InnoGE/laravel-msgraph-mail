# Azure App Registration for laravel-msgraph-mail

Step-by-step guide to create the Entra ID (Azure AD) app registration the package needs. The package uses the OAuth2 client-credentials flow, so the app needs the Microsoft Graph `Mail.Send` **application** permission with admin consent. An Entra ID admin role is required for the consent step.

Walk the user through these steps in order and collect the values for their `.env` along the way. In step 3, choose ONE credential type: a client secret (simplest) or a certificate (no expiry-outage risk; supported by package 2.x).

## 1. Create the app registration

1. Open the [Microsoft Entra admin center](https://entra.microsoft.com) (or Azure Portal → Microsoft Entra ID).
2. Go to **Identity → Applications → App registrations → New registration**.
3. Name it something recognizable, e.g. `laravel-mail-<app-name>`.
4. Supported account types: **Accounts in this organizational directory only** (single tenant).
5. Leave Redirect URI empty (not needed for client-credentials flow) and click **Register**.

## 2. Collect tenant and client IDs

On the app's **Overview** page:

- **Application (client) ID** → `MICROSOFT_GRAPH_CLIENT_ID`
- **Directory (tenant) ID** → `MICROSOFT_GRAPH_TENANT_ID`

Both are GUIDs.

## 3a. Create a client secret (option A)

1. Go to **Certificates & secrets → Client secrets → New client secret**.
2. Add a description and pick an expiry (max 24 months).
3. Copy the **Value** column immediately — it is only shown once. Do **not** copy the "Secret ID" column; that GUID is not the secret.
4. This is `MICROSOFT_GRAPH_CLIENT_SECRET`.

Remind the user to note the expiry date somewhere — mail sending will break with `AADSTS7000215` when the secret expires, and a new secret must be created and deployed.

## 3b. Upload a certificate (option B, package 2.x)

1. Generate a certificate + key, e.g.: `openssl req -x509 -newkey rsa:2048 -keyout private-key.pem -out certificate.pem -days 730 -nodes -subj "/CN=laravel-mail"`
2. Go to **Certificates & secrets → Certificates → Upload certificate** and upload `certificate.pem`; verify the shown thumbprint matches `openssl x509 -in certificate.pem -noout -fingerprint -sha1`.
3. Configure the mailer's `client_certificate` block with the certificate and private key (PEM content or file paths); keep the private key out of version control and readable only by the app user.
4. No `client_secret` needed. After swapping credentials, run `php artisan cache:clear` (the old token stays cached otherwise).

## 4. Grant the Mail.Send application permission

1. Go to **API permissions → Add a permission → Microsoft Graph**.
2. Choose **Application permissions** (not "Delegated permissions" — the package authenticates as the app, not as a signed-in user).
3. Search for and check **Mail.Send**, then click **Add permissions**.
4. If the app should send mails with attachments over ~3 MB total, also check **Mail.ReadWrite** (package 2.x sends large mails via drafts + upload sessions).
5. Click **Grant admin consent for `<tenant>`** and confirm. The Status column must show a green check ("Granted for ..."). Without this step every send fails with `403 ErrorAccessDenied`.

The default "User.Read" delegated permission that Azure adds automatically can be removed; it is not used.

Note: permissions granted later are only picked up after the cached token expires — run `php artisan cache:clear` after any permission change.

## 5. Configure and verify

Set the collected values in `.env`:

```env
MAIL_MAILER=microsoft-graph
MICROSOFT_GRAPH_TENANT_ID=<Directory (tenant) ID>
MICROSOFT_GRAPH_CLIENT_ID=<Application (client) ID>
MICROSOFT_GRAPH_CLIENT_SECRET=<secret Value>
MAIL_FROM_ADDRESS=sender@yourtenant.com
```

`MAIL_FROM_ADDRESS` must be the primary SMTP address of an existing mailbox (licensed user or shared mailbox) in the tenant.

Verify with a test send, e.g. via `php artisan tinker`:

```php
Mail::raw('Graph mail test', fn ($m) => $m->to('you@yourtenant.com')->subject('Graph test'));
```

- Success: the mail arrives; no exception.
- `403 ErrorAccessDenied`: consent missing (step 4) — note that permission/consent changes can take a few minutes to propagate.
- `404 ErrorInvalidUser`: `MAIL_FROM_ADDRESS` is not a real mailbox's primary address.
- `AADSTS7000215`: wrong secret value (often the Secret ID was copied instead of the Value).
- After fixing credentials, run `php artisan config:clear` and `php artisan cache:clear` (the access token is cached).

## Security note

The `Mail.Send` application permission allows the app to send mail as **any** mailbox in the tenant. To restrict the app to specific mailboxes, an Exchange Online admin can scope it with an Application Access Policy / RBAC for Applications — see [Limiting application permissions to specific Exchange Online mailboxes](https://learn.microsoft.com/en-us/graph/auth-limit-mailbox-access).
