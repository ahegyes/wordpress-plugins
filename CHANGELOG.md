# Changelog

Monorepo-level changes (scaffold, workflows, shared tooling). Per-plugin changelogs live under `plugins/<name>/CHANGELOG.md`.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add`. Aggregate into a release with `composer changelog:write`.

<!-- Start changelog -->

## 1.0.0 - unreleased

### Added
- Initial monorepo, which splits each plugin to its own mirror and tests the plugins side by side.

<!-- End changelog -->
