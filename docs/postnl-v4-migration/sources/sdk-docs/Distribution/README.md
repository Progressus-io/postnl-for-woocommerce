# SDK Distribution — Internal Runbook

This document describes how the `postnl/api-client-sdk` package is distributed to authorized customers via [Private Packagist](https://packagist.com) and how to manage ongoing operations.

---

## Architecture overview

```
Private GitHub Repo (Postnl-Production/api-client-sdk)
    ↓  GitHub App webhook (on tag push)
Private Packagist (repo.packagist.com/postnl/)
    ↓  HTTP basic auth (customer token)
Customer's Composer install
```

---

## Initial setup (one-time)

### 1. Create a Private Packagist account

Sign up at https://packagist.com with a paid organization plan. Create an organization named `postnl`.

### 2. PoC — add the repository manually

Before enabling the GitHub App, validate the distribution flow with a manual repository connection:

#### Option A — Deploy Key (SSH, no stored credential needed)

1. In the Private Packagist dashboard go to **Settings → Credentials** (`/orgs/postnl/credentials`).
2. Scroll to the **SSH Access** section and copy the Packagist-generated SSH public key.
3. In the GitHub repository go to **Settings → Deploy keys → Add deploy key**.
   - Title: `Private Packagist`
   - Key: paste the SSH public key from step 2
   - Leave **Allow write access** unchecked
4. In the Private Packagist dashboard go to **Packages → Add Package**.
5. Set the repository URL to the **SSH form** (not HTTPS):
   ```
   git@github.com:Postnl-Production/api-client-sdk.git
   ```
   Leave credentials as **No Credentials** — the deploy key handles authentication.
6. Click **Create**.

> **Note:** The SSH deploy key approach updates packages only sporadically (no webhook). This is acceptable for a PoC. For production, use the GitHub App (step 3) or a stored credential (Option B below) which enables webhooks and near-instant updates.

#### Option B — Fine-Grained PAT (enables webhooks)

1. Create a GitHub Fine-Grained PAT scoped to the `api-client-sdk` repository with `Contents: Read` permission.
2. In the Private Packagist dashboard go to **Settings → Credentials → Add Credential**.
   - Type: HTTP Basic / Token
   - Enter the PAT as the password
3. In the Private Packagist dashboard go to **Packages → Add Package**.
4. Enter the HTTPS URL: `https://github.com/Postnl-Production/api-client-sdk`
5. Select the stored credential from step 2.
6. Click **Create**.

---

After either option, confirm that:

- `postnl/api-client-sdk` is discovered and its tags/branches are indexed.
- The `dev-main → 1.x-dev` alias resolves (defined in `composer.json`).

Once the PoC passes, replace the PAT/deploy-key credential with the GitHub App integration below.

### 3. Security-compliant integration — GitHub App

Private Packagist provides a native GitHub App integration that avoids personal tokens:

1. In the Private Packagist dashboard go to **Integration → GitHub App**.
2. Install the Private Packagist GitHub App on the `Postnl-Production` GitHub organization.
3. During installation, grant access to **only** the `api-client-sdk` repository.
4. Private Packagist automatically installs a webhook on the repository.
5. From this point, every new tag pushed to GitHub triggers an automatic package index update within ~30 seconds.

---

## Releasing a new version

1. Ensure `composer.json` version constraints are updated and release notes are prepared.
2. Push a semver tag:
   ```bash
   git tag v1.2.0
   git push origin v1.2.0
   ```
3. The GitHub App webhook notifies Private Packagist, which indexes the new version automatically. No manual action required.
4. Verify the new version is visible in the Private Packagist dashboard under **Packages → postnl/api-client-sdk**.

---

## Customer access management

### Issuing a customer token

1. In the Private Packagist dashboard go to **Authentication Tokens → Create Token**.
2. Set scope to **Read** (package download only).
3. Optionally associate the token with a specific customer or team in the dashboard.
4. Share the token with the customer over a secure channel (not email plain text).
5. Provide the customer with the installation instructions from `README.md`.

### Revoking a customer token

1. In the Private Packagist dashboard go to **Authentication Tokens**.
2. Find the token by customer name or token prefix.
3. Click **Revoke**. The token is immediately invalidated.
4. Inform the customer that their token has been revoked and issue a replacement if needed.

### Rotating a token

Follow the revoke steps above, then issue a new token and deliver it to the customer. There is no in-place rotation — revoke and re-issue.

---

## Customer installation reference

Customers add the following to their `composer.json`:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://repo.packagist.com/postnl/"
        },
        {
            "packagist.org": false
        }
    ]
}
```

And authenticate via `auth.json` (project root or `~/.composer/`):

```json
{
    "http-basic": {
        "repo.packagist.com": {
            "username": "token",
            "password": "CUSTOMER_TOKEN"
        }
    }
}
```

> **Note:** `token` is a literal string required by Private Packagist — do not replace it with a username.

Or via environment variable (CI/CD):

```bash
export COMPOSER_AUTH='{"http-basic":{"repo.packagist.com":{"username":"token","password":"CUSTOMER_TOKEN"}}}'
```

Install:

```bash
composer require postnl/api-client-sdk
```

> **Tip:** If the project previously pulled the SDK via a local `path` repository (symlink), delete `composer.lock` before running `composer install` to force Composer to re-resolve dependencies from Private Packagist:
> ```bash
> rm composer.lock && composer install
> ```

---

## Where to find things

| Resource | Location |
|----------|----------|
| Private Packagist dashboard | https://packagist.com/orgs/postnl |
| Credentials (SSH key / PAT) | https://packagist.com/orgs/postnl/credentials |
| Package page | https://packagist.com/packages/postnl/api-client-sdk |
| GitHub deploy keys | GitHub repo → Settings → Deploy keys |
| GitHub App settings | GitHub org → Settings → GitHub Apps |
| Repository webhook | GitHub repo → Settings → Webhooks |

---

## Security notes

- The GitHub App grants **read-only** access to the single repository. Do not expand its permissions.
- Customer tokens are **read-only** and scoped to package download. They do not grant repository access.
- `auth.json` must never be committed to source control (enforced by `.gitignore`).
- For CI/CD pipelines, use the `COMPOSER_AUTH` environment variable injected from a secrets manager.
- Rotate tokens immediately if a credential leak is suspected.
