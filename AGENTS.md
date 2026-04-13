# AGENTS.md

This file provides guidance to AI agents (Warp, Copilot, Claude Code, etc.) when working with code in this repository.

## Project Overview

**Repository**: multiflexi-web (part of the MultiFlexi suite)
**Debian package**: `multiflexi-web`
**Type**: PHP Web Application — Apache/Nginx-served UI
**Purpose**: Web interface for the MultiFlexi platform — multi-tenant job scheduling and automation for accounting integrations (AbraFlexi, Pohoda, etc.)
**License**: MIT
**Homepage**: https://multiflexi.eu/

This is the web front-end component of the MultiFlexi suite. It sits on top of:
- `php-vitexsoftware-multiflexi-core` — domain models (Company, Application, RunTemplate, Job, Credential, …)
- `multiflexi-database` — Phinx DB migrations
- `multiflexi-server` — REST API (OpenAPI/Slim 4)
- `multiflexi-cli` — CLI management tool
- `multiflexi-executor` — job execution daemon
- `multiflexi-scheduler` — cron-based job scheduling daemon

All suite components share the same MultiFlexi database (configured via `DB_*` env vars in `.env` or `/etc/multiflexi/multiflexi.env`).

## High-Level Architecture

### Entry Points
All web pages live under `src/` as standalone PHP files (e.g. `src/dashboard.php`, `src/company.php`, `src/job.php`). Each file bootstraps via `src/init.php` which initialises `Ease\Shared`, starts a secure session, and sets security headers.

### Namespaces (PSR-4, all under `src/`)
| Namespace | Path | Purpose |
|---|---|---|
| `MultiFlexi\` | `src/MultiFlexi/` | Local extensions on top of core |
| `MultiFlexi\Ui\` | `src/MultiFlexi/Ui/` | UI components (forms, panels, wizards, charts) |
| `MultiFlexi\Ui\Action\` | `src/MultiFlexi/Ui/Action/` | Action form helpers (12 action types) |
| `MultiFlexi\Ui\CredentialType\` | `src/MultiFlexi/Ui/CredentialType/` | Credential form helpers (from addon packages) |
| `MultiFlexi\Api\` | `src/MultiFlexi/Api/` | REST API controllers |
| `MultiFlexi\Api\Auth\` | `src/MultiFlexi/Api/Auth/` | API authentication |
| `MultiFlexi\Api\Server\` | `src/MultiFlexi/Api/Server/` | API server glue |
| `MultiFlexi\Audit\` | `src/MultiFlexi/Audit/` | Audit logging |
| `MultiFlexi\GDPR\` | `src/MultiFlexi/GDPR/` | GDPR request handling (Articles 15–17) |
| `MultiFlexi\Notifications\` | `src/MultiFlexi/Notifications/` | Email notifications |

### Key Ui Component Groups
- **Wizards**: `ActivationWizard`, `CredentialWizard`, `ConfigurationWizard` — multi-step on-boarding flows
- **Dashboard**: `DashboardMetricsCards`, `DashboardStatusCards`, `DashboardIntervalChart`, `DashboardJobsByCompanyChart`, `DashboardJobsByAppChart`, `DashboardTimelineChart`, `DashboardRecentJobsTable`
- **Forms**: `CompanyEditorForm`, `UserForm`, `EnvsForm`, `AppLaunchForm`, `RuntemplateLaunchForm`, …
- **Actions** (`Ui/Action/`): `ChainRuntemplate`, `LaunchJob`, `TriggerJenkins`, `Github`, `RedmineIssue`, `WebHook`, `Zabbix`, `CustomCommand`, `Sleep`, `Stop`, `Reschedule`, `ToDo`
- **Security**: `src/MultiFlexi/Security/` — `SessionManager`, `CsrfProtection`, `DataEncryption`, `RateLimiter`, `ApiRateLimiter`, `IpWhitelist`, `TwoFactorAuth`, `PasswordValidator`, `SecurityAuditLogger`
- **GDPR**: `src/MultiFlexi/GDPR/`, `DataExport/`, `DataErasure/`, `DataRetention/` — full Article 15/16/17 implementation

### Real-time
`src/websocket-server.php` runs a Ratchet WebSocket server for live job status streaming.

### Telemetry
`src/MultiFlexi/Telemetry/OtelMetricsExporter.php` exports metrics via OpenTelemetry SDK to an OTLP endpoint.

## Development Commands

All standard targets are in `Makefile`:

```bash
make vendor                      # composer install
make cs                          # php-cs-fixer fix (PSR-12)
make static-code-analysis        # PHPStan (phpstan-default.neon.dist)
make static-code-analysis-baseline  # regenerate PHPStan baseline
make tests                       # vendor/bin/phpunit tests
make autoload                    # composer update (regenerate autoload)
make docs                        # Sphinx HTML docs
make clean                       # remove vendor/, composer.lock, SQLite DB
```

Run a single test:
```bash
vendor/bin/phpunit tests/src/MultiFlexi/SomeClassTest.php
vendor/bin/phpunit --filter 'ClassName::testMethod'
```

Lint a file before committing:
```bash
php -l src/MultiFlexi/Ui/SomeClass.php
```

GDPR Article 16 DB migration (run once after upgrading):
```bash
make gdpr-migration
```

## Configuration & Environment

`src/init.php` reads these keys from `.env` (or `/etc/multiflexi/multiflexi.env` on installed systems):

| Key | Purpose |
|---|---|
| `DB_CONNECTION` | `mysql` / `pgsql` / `sqlite` |
| `DB_HOST`, `DB_PORT` | Database host/port |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database credentials |
| `ENCRYPTION_MASTER_KEY` | AES-256 master key for credential encryption |
| `EASE_LOGGER` | Logger config (e.g. `syslog\|console`) |
| `APP_DEBUG` | Enable debug output (`true`/`false`) |
| `SESSION_TIMEOUT` | Session idle timeout in seconds (default 14400) |
| `SESSION_REGENERATION_INTERVAL` | Session ID rotation interval (default 900) |
| `SESSION_STRICT_USER_AGENT` | Bind session to User-Agent (default `true`) |
| `SESSION_STRICT_IP_ADDRESS` | Bind session to IP (default `false`) |
| `OTEL_ENABLED`, `OTEL_EXPORTER_OTLP_ENDPOINT` | OpenTelemetry export |
| `ZABBIX_SERVER`, `ZABBIX_HOST` | Zabbix monitoring integration |
| `MULTIFLEXI_URL` | Base URL of the web application |

## Coding Standards

- PHP 8.1+, strict types everywhere (`declare(strict_types=1)`)
- PSR-12 via `ergebnis/php-cs-fixer-config` (config: `.php-cs-fixer.dist.php`)
- PSR-4 autoloading — never add manual `require`/`include`
- Use `_()` for all user-visible strings (gettext i18n)
- Docblocks with typed parameters and return types on all classes/methods
- Create or update a PHPUnit test whenever you create or modify a class
- Run `make cs` and `make static-code-analysis` before committing

## Debian Packaging

```bash
make debs          # builds .deb (debuild -i -us -uc -b)
make redeb         # purges installed package, rebuilds, installs with gdebi
make debs2deb      # moves built .deb files to dist/ and bundles them
```

The `debian/control` binary package `multiflexi-web` depends on:
`multiflexi-common`, `php-vitexsoftware-multiflexi-core`, Bootstrap 4 widget libraries, DataTables JS, jQuery Selectize, Font Awesome, and a sodium extension for encryption.

## Docker

```bash
make dimage        # builds vitexsoftware/multiflexi image
make drun          # builds + runs on :8080, opens in browser
make demoimage     # builds vitexsoftware/multiflexi-demo image
make demorun       # runs demo on :8282 (login: demo/demo)
```

## Pages Reference (src/*.php)

| File | Purpose |
|---|---|
| `dashboard.php` | Main dashboard with analytics charts |
| `companies.php` / `company.php` | Company listing and detail |
| `companyapps.php` / `companyapp.php` | Per-company app assignment |
| `apps.php` / `app.php` | Application catalogue |
| `runtemplates.php` / `runtemplate.php` | Run template management |
| `jobs.php` / `job.php` | Job listing and detail |
| `credentials.php` / `credential.php` | Credential management |
| `credentialtypes.php` / `credentialtype.php` | Credential type management |
| `credentialprototypes.php` / `credentialprototype.php` | Credential prototype management |
| `activation-wizard.php` | Step-by-step app activation wizard |
| `credential-wizard.php` | Step-by-step credential setup wizard |
| `users.php` / `user.php` | User management |
| `eventsources.php` / `eventsource.php` | Event source configuration |
| `eventrules.php` / `eventrule.php` | Event rule configuration (event → RunTemplate mapping) |
| `data-export.php` / `data-export-page.php` | GDPR Article 15 (data export) |
| `admin-data-corrections.php` | GDPR Article 16 (data correction approval) |
| `gdpr-user-deletion-request.php` / `admin-deletion-requests.php` | GDPR Article 17 (right to erasure) |
| `data-retention-admin.php` | Data retention policy management |
| `consent-preferences.php` / `consent-api.php` | Cookie consent management |
| `websocket-server.php` | Ratchet WebSocket daemon for live job output |
| `login.php` / `logout.php` / `createaccount.php` / `passwordrecovery.php` | Authentication |
| `init.php` | Bootstrap — shared by all pages |

## Suite Architecture Context

See the top-level `~/Projects/Multi/AGENTS.md` for ecosystem-wide context including:
- Two build pipelines (production `repo.multiflexi.eu` vs testing `repo.vitexsoftware.com`)
- Dependency build order across all packages
- Jenkins pipeline conventions
- Common environment variables and coding standards

## Related Resources

- **Demo**: https://demo.multiflexi.eu/ (login: demo / demo)
- **Docs**: https://multiflexi.readthedocs.io/
- **GitHub**: https://github.com/VitexSoftware/MultiFlexi
- **Production repo**: https://repo.multiflexi.eu/
- **Jenkins**: https://jenkins.proxy.spojenet.cz/job/MultiFlexi/
