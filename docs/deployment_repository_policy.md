# Deployment Repository Policy

## Purpose

This repository is intended to be a deployable installation source for hosts that clone or pull the project, point the web root to `public/`, and complete setup through the built-in `/install` web installer.

## Standard Host Flow

1. Clone or pull the repository.
2. Point the web root to `public/`.
3. Open `/install` in the browser.
4. Let the installer create `.env`, validate requirements, configure the database, and complete installation.
5. If CLI is available, run `/usr/local/bin/php83 artisan system:smoke-check` after install.

## Repository Packaging Rules

The repository should include install-time source and should exclude runtime-local state.

Included:

- application source
- built-in `/install` flow
- `.env.example`
- bundled templates, modules, and plugins
- tracked frontend assets required by the installer or first boot
- `vendor-bundle.zip`
- `vendor-bundle.json`

Excluded:

- `.env`
- runtime logs
- framework cache, sessions, and compiled views
- `bootstrap/cache` generated files
- `node_modules`
- `storage/app/template-releases`
- local hot-reload files and machine-local artifacts

## Writable Directories

These paths must exist in the repository with placeholder files so a fresh clone remains installable:

- `storage/app/`
- `storage/logs/`
- `storage/framework/cache/`
- `storage/framework/cache/data/`
- `storage/framework/sessions/`
- `storage/framework/views/`
- `bootstrap/cache/`

## Vendor Bundle Policy

`vendor-bundle.zip` and `vendor-bundle.json` are intentionally tracked.

They are part of the shared-hosting and installer-friendly deployment story and should remain in the repository unless the existing installer and vendor bundle install path are formally retired.

## Operator Notes

- Do not pre-create `.env` for normal web installation.
- Do not treat cron as part of initial installation.
- Use cron or scheduler configuration only after installation is complete.
- If a template release fails after update, run `template:rollback` immediately.
