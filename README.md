# WP Diagnostic Toolkit

**A lightweight diagnostic toolkit for WordPress developers and small agencies.**

WP Diagnostic Toolkit helps developers inspect a WordPress installation's environment, plugins, and server configuration from the WordPress admin dashboard.

The goal is to make technical information easier to access, identify potential configuration issues, and provide a starting point for troubleshooting.

## Features

The plugin currently includes diagnostic modules for:

- **Environment:** WordPress and PHP versions, database information, debugging configuration, memory limits, HTTPS configuration, and loopback requests.
- **Plugins:** Installed plugins, activation status, and available updates.
- **Server:** PHP configuration, resource limits, and extension availability.
- **Dashboard:** A centralized view of diagnostic results, grouped by status.
- **Settings:** An administration section for toolkit-related settings.

Diagnostic results use statuses such as `pass`, `info`, `warning`, and `critical` to help distinguish normal conditions from potential issues.

> WP Diagnostic Toolkit is intended as a technical troubleshooting aid, not as a replacement for specialized performance, security, or SEO auditing tools.

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
│   └── ServerDiagnostic
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

## Design Principles

- **Read-only diagnostics:** Inspect the environment without intentionally modifying site content or configuration.
- **Modular architecture:** Keep checks isolated so they can be maintained and extended independently.
- **Actionable results:** Present findings with a clear status and supporting information.
- **Practical scope:** Prioritize checks that help developers investigate real WordPress issues.
- **Incremental development:** Improve the accuracy and reliability of existing checks before expanding the feature set.

## Current Status

**Version:** 0.2.0

This project is under active development. Diagnostic coverage, compatibility handling, result classification, and administration workflows may change between versions.

The current implementation provides the foundation for a modular WordPress diagnostic tool. It should not yet be considered a comprehensive audit or monitoring solution.

## Roadmap

Potential areas for future improvement include:

- [ ] Improve diagnostic result consistency and documentation.
- [ ] Refine plugin activation and update detection, including multisite considerations.
- [ ] Review server checks to distinguish actual requirements from recommendations.
- [ ] Reduce false positives in environment and connectivity checks.
- [ ] Improve dashboard feedback and diagnostic execution workflow.
- [ ] Add automated tests for diagnostic logic.
- [ ] Improve error handling and diagnostic reporting.
- [ ] Expand checks based on practical troubleshooting needs.

## Contributing

Contributions, bug reports, and suggestions are welcome.

When reporting an issue, include relevant details where possible:

- WordPress version
- PHP version
- Database type and version
- Steps to reproduce the issue
- Relevant diagnostic output or error messages

Please avoid sharing credentials, private configuration values, or sensitive production data.

## Disclaimer

Diagnostic findings are intended to support technical investigation. A warning does not necessarily indicate a failure, and a passing check does not guarantee that a WordPress installation is secure, performant, or error-free.

Always validate findings against the specific environment before making changes.

## License

The project's license should be specified here once selected.
