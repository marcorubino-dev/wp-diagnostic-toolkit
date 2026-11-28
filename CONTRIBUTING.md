# Contributing

Thank you for your interest in WP Diagnostic Toolkit.

The project is under active development. The guidelines below help keep bug reports, technical discussions, and proposed changes focused and reproducible.

## Before opening an issue

Please include:

- WordPress and PHP versions.
- The plugin version.
- Relevant environment details (web server, hosting constraints, and related plugins when applicable).
- The exact steps needed to reproduce the behavior.
- Expected behavior and actual behavior.
- Relevant diagnostic output or logs, with credentials, personal data, tokens, and private paths removed.

For security-sensitive reports, do not publish exploitable details or sensitive information in a public issue. Contact the maintainer privately before disclosure.

## Before proposing a change

For substantial changes, open an issue or discussion first. Explain:

- The problem being addressed.
- The diagnostic behavior you intend to change.
- Why the proposed approach is appropriate.
- Any compatibility or security implications.

Keep changes scoped. Avoid mixing unrelated refactors with bug fixes.

## Code expectations

- Preserve the read-only nature of diagnostic checks.
- Do not introduce automatic repairs or site mutations into diagnostic routines.
- Follow the existing PHP style and architecture.
- Handle unavailable environment data conservatively.
- Keep diagnostic findings explicit and evidence-based.
- Avoid exposing secrets, credentials, personal data, or sensitive server details.
- Add or update regression tests for behavior changes.

## Validation

Before submitting a pull request, run the relevant CLI tests and PHP syntax checks. The GitHub Actions workflow runs the project's regression suite across its configured PHP versions.

Describe the tests you ran and identify any environment-specific limitations.

## Pull requests

A pull request should explain:

1. The problem and its impact.
2. The implementation and affected components.
3. Tests performed and their results.
4. Any known limitations or follow-up work.

Do not include unrelated formatting changes or generated files unless they are required.

## Scope and review

The maintainer may request changes, defer a proposal, or close a contribution that does not fit the project's current scope. Opening a pull request does not imply acceptance or a release commitment.

Licensing and contributor terms for submitted contributions will be clarified with the maintainer as needed; please do not assume that opening a pull request changes the license of your work.
