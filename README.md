# ServSys WordPress

WordPress-based replacement for the legacy ServSys field service management system.

## Development status

ServSys WordPress is under active development. The original Java ServSys repository remains the historical reference implementation; this repository is a new WordPress implementation and does not reproduce the legacy laptop/server synchronization architecture.

Current development target: **v0.1.0**

### v0.1 planned scope

- Customers and service sites
- Equipment
- Service calls
- Service-call equipment relationships
- Service charge lines
- Completion codes
- Follow-ups
- Printable service report
- WordPress roles and capabilities

## Branches

- `main` — protected release branch; merge only validated release-ready work.
- `develop` — active integration branch for development.

Feature work should branch from `develop` and return to `develop` after testing.

## Requirements

- WordPress
- PHP 7.4 or later
- Composer 2 for development dependencies

## Development

Install development dependencies:

```bash
composer install
```

Run PHP linting:

```bash
composer lint
```

Run unit tests:

```bash
composer test
```

GitHub Actions tests PHP 7.4 through PHP 8.5.

## Architecture

ServSys uses custom relational WordPress tables for operational data and WordPress users, authentication, roles, capabilities, mail, media, and REST infrastructure.

Legacy synchronization fields and mechanisms such as `servsync`, laptop database replication, and server upload/download actions are intentionally not part of the new architecture. Modern offline/PWA support may be designed separately in a future release.
