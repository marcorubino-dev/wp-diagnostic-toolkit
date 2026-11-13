<div align="center">

# WP Diagnostic Toolkit

**Evidence-driven, modular diagnostics for WordPress developers and small agencies.**

<img src="https://img.shields.io/badge/Platform-WordPress-21759B?logo=wordpress&logoColor=white" alt="WordPress" />
<img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white" alt="PHP 7.4+" />
<img src="https://img.shields.io/badge/License-GPL--2.0--or--later-2B7A4B" alt="GPL 2.0 or later" />
<img src="https://img.shields.io/badge/Status-Active%20development-64748B" alt="Active development" />
<a href="https://github.com/marcorubino-dev/wp-diagnostic-toolkit/actions/workflows/php-tests.yml"><img src="https://github.com/marcorubino-dev/wp-diagnostic-toolkit/actions/workflows/php-tests.yml/badge.svg?branch=main" alt="PHP Tests" /></a>

</div>

WP Diagnostic Toolkit helps developers inspect a WordPress installation's environment, plugins, active theme, server configuration, filesystem capacity, HTTP/SSL behavior, and selected WooCommerce/multilingual integration signals from the WordPress admin dashboard.

Its purpose is to make technical information easier to access, surface potential configuration issues, and provide a starting point for evidence-based troubleshooting.

> **Project status:** Active development. The toolkit is a diagnostic aid, not a comprehensive audit or monitoring solution.

## At a glance

- **Platform:** WordPress
- **Minimum PHP version:** 7.4, according to the current plugin header
- **Approach:** Modular, read-only diagnostics
- **License:** GPL-2.0-or-later

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Testing](#testing)
- [How it works](#how-it-works)
- [WooCommerce and multilingual troubleshooting](#woocommerce-and-multilingual-troubleshooting)
- [Design principles](#design-principles)
- [Current status](#current-status)
- [Architecture notes](#architecture-notes)
- [Contributing](#contributing)
- [Disclaimer](#disclaimer)
- [License](#license)

## Features

| Area | What it checks |
| --- | --- |
| **Environment** | WordPress and PHP versions, database information, debugging configuration, memory limits, and loopback requests. |
| **HTTP and SSL** | HTTP response behavior, HTTPS availability, and basic SSL-related signals. |
| **Plugins** | Installed plugins, activation status, and available updates. |
| **Theme** | Active theme information and available theme metadata. |
| **Server** | PHP configuration, resource limits, and extension availability. |
| **Filesystem** | Available and total disk space reported for the filesystem containing the WordPress installation. |
| **WooCommerce and multilingual** | WooCommerce availability, detection of selected multilingual plugins, and guidance for investigating language-switch/cart symptoms. |
| **Debug Center** | Read-only inspection of WordPress/PHP debugging configuration and safe metadata checks for an eligible debug-log target. Log contents are never opened or displayed. |
| **Dashboard** | A centralized view of diagnostic results, grouped by status, with report-copy actions. |
| **Settings** | An administration section for toolkit-related settings, including appearance preferences. |

Results use statuses such as `pass`, `info`, `warning`, and `critical` to help distinguish normal conditions from potential issues.

**Scope note:** The initial multilingual module is an environment-level starting point. It does not capture a visitor's live cart, prove translation mapping correctness, or automatically identify a root cause.

**Debug Center boundary:** It is an inspection surface, not a log viewer, repair tool, or server observability platform. It does not read, download, or modify log files.

## Requirements

- WordPress
- PHP 7.4 or later, according to the current plugin header

Actual compatibility depends on the WordPress version, PHP runtime, and the functionality being inspected. Requirements will be refined as the project evolves.

## Installation

1. Download or clone this repository.
2. Place the `wp-diagnostic-toolkit` directory inside `wp-content/plugins/`.
3. Activate **WP Diagnostic Toolkit** from the WordPress admin dashboard.
4. Open the toolkit menu in the WordPress admin area to review diagnostic results.

For local development, install the plugin in a test WordPress environment before using it on a production site.

## Testing

The repository includes lightweight PHP CLI tests. They do not require PHPUnit or a WordPress bootstrap; tests that exercise WordPress-dependent diagnostics provide controlled stubs for the WordPress HTTP and environment APIs.

The regression suite covers result normalization and validation, legacy adaptation, malformed-result isolation, duplicate-ID integrity errors, module execution, dashboard and admin rendering, environment and server checks, HTTP/SSL behavior, filesystem metadata, theme detection, and Debug Center path-safety boundaries.

Run the test scripts from the repository root:

```bash
php tests/PluginDiagnosticTest.php
php tests/RuleResultTest.php
php tests/DiagnosticManagerTest.php
php tests/DashboardViewTest.php
php tests/ServerDiagnosticTest.php
php tests/DiskSpaceDiagnosticTest.php
php tests/HttpSslDiagnosticTest.php
php tests/EnvironmentDiagnosticTest.php
php tests/ThemeDiagnosticTest.php
php tests/AdminMenuTest.php
php tests/DebugCenterTest.php
```

The HTTP/SSL regression test covers successful responses, redirects, client and server errors, HEAD-to-GET fallback, generic transport failures, TLS verification errors, and non-HTTPS URLs. The environment test covers WordPress/database version reporting, HTTPS detection, loopback success, redirects, client/server errors, and transport failures. The theme test covers installed and active themes, child-theme parent detection, missing parents, undeclared versions, and update transient states.

The GitHub Actions workflow runs PHP syntax validation and the CLI regression suite against PHP 7.4 through 8.4 on pushes and pull requests targeting `main`.

These tests verify diagnostic behavior against controlled API responses. They are not a substitute for integration testing in a real WordPress installation or validation against different hosting, proxy, DNS, and TLS configurations.

## How it works

The plugin uses a modular architecture to keep diagnostic logic separate from the administration interface.

```text
WP Diagnostic Toolkit
├── Core
│   ├── DiagnosticInterface
│   ├── DiagnosticManager
│   ├── RuleResult
│   ├── LegacyResultAdapter
│   └── DiagnosticResultPresenter
├── Diagnostics
│   ├── EnvironmentDiagnostic
│   ├── HttpSslDiagnostic
│   ├── PluginDiagnostic
│   ├── ThemeDiagnostic
│   ├── ServerDiagnostic
│   ├── DiskSpaceDiagnostic
│   └── MultilingualWooCommerceDiagnostic
├── Debug
│   ├── DebugConfigurationInspector
│   └── DebugLogTargetInspector
└── Admin
    ├── Dashboard
    ├── Environment
    ├── Plugins
    ├── Server
    ├── Debug Center
    └── Settings
```

### Diagnostic interface

Each diagnostic module implements a common interface that defines its identifier, display name, and execution method.

### Diagnostic manager

The manager registers diagnostic modules, supports execution of selected registered modules, normalizes legacy arrays and native `RuleResult` objects, and isolates malformed results without discarding valid siblings. Duplicate result IDs are reported as integrity errors; the first result is retained. Module execution failures are represented explicitly so other modules can continue.

### Normalized result contract

`RuleResult` defines a stable result shape with an identifier, title, outcome, severity, confidence, description, value, recommendation, evidence, and module identifier. `LegacyResultAdapter` provides a migration path for existing diagnostic arrays. `DiagnosticResultPresenter` preserves the field names and status values expected by the current admin templates while exposing the normalized fields.

### Admin interface

The administration layer presents diagnostic results in the WordPress dashboard, keeping presentation separate from the diagnostic checks themselves. Diagnostic reports can also be copied for sharing or further investigation.

### Debug Center

Debug Center presents sanitized configuration states and, when permitted by the filesystem boundary, a safe relative location and metadata status for the WordPress debug-log target. It deliberately does not acquire log contents. See the [Debug Center v1 specification](docs/architecture/debug-center-v1-specification.md) for its security and scope requirements.

## WooCommerce and multilingual troubleshooting

The initial module provides a starting point when investigating issues such as products or cart contents changing after a language switch.

### Current checks

- Detect whether WooCommerce is loaded.
- Detect selected multilingual plugins using active plugin entries and available runtime APIs.
- Explain what the admin snapshot cannot prove about a visitor's cart/session.
- Identify the need for adapter-specific translation mapping checks.

### Planned follow-up

- Add integration adapters for the multilingual plugin installed on the target site.
- Add an explicit, temporary frontend capture workflow for before/after language-switch comparisons.
- Compare product/variation IDs, quantities, price values, language, URL, and cart state.
- Add a privacy-conscious exportable report with secrets and customer data excluded by default.

The current module does not modify products, prices, translations, sessions, or cart contents. Findings are not root-cause diagnoses.

## Design principles

- **Read-only diagnostics:** Inspect the environment without intentionally modifying site content or configuration.
- **Modular architecture:** Keep checks isolated so they can be maintained and extended independently.
- **Actionable results:** Present findings with a clear status and supporting information.
- **Practical scope:** Prioritize checks that help developers investigate real WordPress issues.
- **Incremental development:** Improve the accuracy and reliability of existing checks before expanding the feature set.

## Current status

**Version:** 0.3.0 (development)

The toolkit currently includes environment, HTTP/SSL, plugin, theme, server, filesystem, and initial WooCommerce/multilingual diagnostics, plus a read-only Debug Center. Diagnostic coverage, compatibility handling, result classification, and administration workflows may change as the project evolves.

The current implementation provides a foundation for a modular diagnostic tool. It should not yet be considered a comprehensive audit or monitoring solution.

## Architecture notes

The repository includes focused architecture documents for areas where contracts and security boundaries matter:

- [Normalized diagnostic result contract](docs/architecture/rule-result-contract.md) — result fields, validation, and compatibility expectations.
- [Debug Center v1 specification](docs/architecture/debug-center-v1-specification.md) — read-only scope, filesystem boundary, privacy rules, and acceptance criteria.
- [Public integration contract](docs/architecture/public-integration-contract.md) — proposed API for external consumers. **Architecture proposal; not implemented.**
- [Companion action engine specification](docs/architecture/action-engine-specification.md) — proposed separation between diagnostics and operations. **Design document; not a claim of shipped functionality.**

## Contributing

Contributions, bug reports, and suggestions are welcome.

When reporting an issue, include relevant details where possible:

- WordPress version
- PHP version
- Database type and version
- Relevant WooCommerce and multilingual plugin versions
- Steps to reproduce the issue
- Relevant diagnostic output or error messages

Please avoid sharing credentials, private configuration values, customer data, or sensitive production data.

## Disclaimer

Diagnostic findings support technical investigation. A warning does not necessarily indicate a failure, and a passing check does not guarantee that an installation is secure, performant, or error-free. Validate findings against the specific environment before making changes.

## License

WP Diagnostic Toolkit is licensed under the GNU General Public License, version 2 or (at your option) any later version. See the [LICENSE](LICENSE) file for the license notice and the [official GPL v2 text](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html).
