# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Dependencies
make vendor                        # Install Composer dependencies

# Code quality
make cs                            # PHP CS Fixer (code style)
make static-code-analysis          # PHPStan static analysis
make static-code-analysis-baseline # Regenerate PHPStan baseline

# Tests
make tests                         # Run PHPUnit test suite
vendor/bin/phpunit tests/SomeTest.php  # Run a single test file

# Documentation
make docs                          # Build Sphinx HTML docs
```

## Architecture

**MultiFlexi Web** is a PHP 8.1+ web UI for the MultiFlexi task automation platform. It orchestrates jobs/runs on AbraFlexi and Pohoda servers via a Bootstrap 4 interface.

### Request Lifecycle

1. Browser hits a file-based route (e.g. `src/app.php`) — routing is file-per-page, not a router.
2. Every page begins with `require_once './init.php'`, which bootstraps: environment, session security, DB, CSRF protection, localization, OpenTelemetry.
3. `WebPage::singleton()` (extends `Ease\TWB4\WebPage`) is the global page object. Protected pages call `WebPage::singleton()->onlyForLogged()`.
4. Action handling is done inline via `?action=` query param switches.
5. Output is rendered by Ease framework component tree (Card, Row, Table, Form etc.) via `WebPage::singleton()->draw()`.

### Key Namespaces (PSR-4)

| Namespace | Path | Purpose |
|-----------|------|---------|
| `MultiFlexi\Ui\` | `src/MultiFlexi/Ui/` | All UI components, page panels, forms |
| `MultiFlexi\Ui\Action\` | `src/MultiFlexi/Ui/Action/` | Extensible action plugins (LaunchJob, Zabbix, WebHook, …) |
| `MultiFlexi\Security\` | `src/MultiFlexi/Security/` | Session, CSRF, brute-force, 2FA, RBAC, encryption |
| `MultiFlexi\Audit\` | `src/MultiFlexi/Audit/` | Security audit logging |
| `MultiFlexi\GDPR\` | `src/MultiFlexi/GDPR/` | GDPR Articles 16/17: correction, erasure, retention, consent |
| `MultiFlexi\Telemetry\` | `src/MultiFlexi/Telemetry/` | OpenTelemetry metrics export |
| Core models | `vendor/vitexsoftware/multiflexi-core` | Company, Job, Application, RunTemplate, etc. |

### Design Patterns

- **Singleton page**: `WebPage::singleton()` / `Shared::singleton()` give global access to the page and shared state.
- **Model classes**: Data entities extend Ease ORM base classes from `multiflexi-core`; FluentPDO handles query building.
- **Lister classes**: Dedicated `*Lister` classes (e.g. `CompanyJobLister`) provide filtered/sorted data access on top of models.
- **Action plugins**: Classes under `Ui/Action/` implement a common interface for extensible task-execution side-effects.
- **UI components**: Ease framework objects (`Card`, `Row`, `Table`, `Form`) are composed together and attached to the page; `draw()` renders the whole tree.

### Frontend

- Bootstrap 4 via Ease TWB4 wrapper — use `fa-*` (Font Awesome) for icons.
- DataTables for sortable/searchable lists; Chart.js / SVGGraph for job charts.
- CSS/JS assets live in `src/css/` and `src/js/`.

### Security & GDPR

Every non-API page gets CSRF protection automatically via `init.php`. Session security (regeneration, user-agent/IP pinning, secure cookies) is managed by `MultiFlexi\Security\SessionManager`. Do not bypass these in new pages.

GDPR consent/erasure flows live in `consent-*.php` and `data-export*.php` and must stay consistent with `MultiFlexi\GDPR\*` classes.

### Localization

Use `_('string')` (gettext) throughout. Translation files are in `i18n/`. `Ease\Locale` is initialized in `init.php`.

### Environment

Configuration comes from `.env` (dev) or `/etc/multiflexi/multiflexi.env` (production). Key vars: `DB_*` (database), `ENCRYPTION_MASTER_KEY`, `SESSION_TIMEOUT`, `SESSION_REGENERATION_INTERVAL`, `LOG_DIRECTORY`.
