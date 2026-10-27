# WP Diagnostic Toolkit

**A lightweight diagnostic toolkit for WordPress developers and small agencies.**

WP Diagnostic Toolkit helps developers inspect a WordPress installation's environment, plugins, server configuration, and selected WooCommerce/multilingual integration signals from the WordPress admin dashboard.

The goal is to make technical information easier to access, identify potential configuration issues, and provide a starting point for evidence-based troubleshooting.

## Features

- **Environment:** WordPress and PHP versions, database information, debugging configuration, memory limits, HTTPS configuration, and loopback requests.
- **Plugins:** Installed plugins, activation status, and available updates.
- **Server:** PHP configuration, resource limits, and extension availability.
- **WooCommerce & multilingual (initial checks):** WooCommerce availability, detection of selected multilingual plugins, and guidance for investigating language-switch/cart symptoms.
- **Dashboard:** A centralized view of diagnostic results, grouped by status.
- **Settings:** An administration section for toolkit-related settings.

Diagnostic results use statuses such as `pass`, `info`, `warning`, and `critical` to help distinguish normal conditions from potential issues.

> The initial multilingual module is an environment-level starting point. It does not yet capture a visitor's live cart, prove translation mapping correctness, or identify a root cause automatically.

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

## How It Works

The plugin uses a modular architecture to keep diagnostic logic separate from the administration interface.

```text
WP Diagnostic Toolkit
├── Core
│   ├── DiagnosticInterface
│   └── DiagnosticManager
├── Diagnostics
│   ├── EnvironmentDiagnostic
│   ├── PluginDiagnostic
│   ├── ServerDiagnostic
│   └── MultilingualWooCommerceDiagnostic
└── Admin
    ├── Dashboard
    ├── Environment
    ├── Plugins
    ├── Server
    └── Settings
```

### Diagnostic Interface

Each diagnostic module implements a common interface that defines its identifier, display name, and execution method.

### Diagnostic Manager

The manager registers diagnostic modules and runs them through a shared entry point.

### Admin Interface

The administration layer presents diagnostic results in the WordPress dashboard, keeping presentation separate from the diagnostic checks themselves.

## Multilingual & WooCommerce Troubleshooting

The initial module is designed to provide safe starting evidence when investigating issues such as products or cart contents changing after a language switch.

Current checks:
- Detect whether WooCommerce is loaded.
- Detect selected multilingual plugins using active plugin entries and available runtime APIs.
- Explain what the admin snapshot cannot prove about a visitor's cart/session.
- Identify the need for adapter-specific translation mapping checks.

Planned follow-up:
- Add integration adapters for the multilingual plugin actually installed on the target site.
- Add an explicit, temporary frontend capture workflow for before/after language-switch comparisons.
- Compare product/variation IDs, quantities, price values, language, URL, and cart state.
- Add a privacy-conscious exportable report with secrets and customer data excluded by default.

The current module does not modify products, prices, translations, sessions, or cart contents. Findings are not root-cause diagnoses.

## Design Principles

- **Read-only diagnostics:** Inspect the environment without intentionally modifying site content or configuration.
- **Modular architecture:** Keep checks isolated so they can be maintained and extended independently.
- **Actionable results:** Present findings with a clear status and supporting information.
- **Practical scope:** Prioritize checks that help developers investigate real WordPress issues.
- **Incremental development:** Improve the accuracy and reliability of existing checks before expanding the feature set.

## Current Status

**Version:** 0.3.0 (development branch)

This project is under active development. Diagnostic coverage, compatibility handling, result classification, and administration workflows may change between versions.

The current implementation provides a foundation for a modular diagnostic tool. It should not yet be considered a comprehensive audit or monitoring solution.

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
