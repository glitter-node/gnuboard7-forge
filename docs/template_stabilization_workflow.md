# Template Stabilization Workflow

## Purpose

This workflow standardizes how operators diagnose, preflight, release, and verify templates before and after template state changes.

In this environment, use `/usr/local/bin/php83 artisan ...` instead of plain `php artisan ...`.

## Standard Release Command

Primary release flow:

```bash
/usr/local/bin/php83 artisan template:release {identifier}
/usr/local/bin/php83 artisan template:release {identifier} --admin
```

This flow runs:

1. `template:preflight`
2. `template:build`
3. `template:update`
4. `template:activate`
5. `config:clear`
6. `cache:clear`
7. `view:clear`
8. `system:smoke-check`

## Preflight-Only Command

Validate bundled template source before release:

```bash
/usr/local/bin/php83 artisan template:preflight {identifier}
/usr/local/bin/php83 artisan template:preflight {identifier} --admin
```

Checks:

- `templates/_bundled/{identifier}` exists
- `template.json` exists and is valid JSON
- `routes.json` exists and is valid JSON
- `components.json` exists and is valid JSON
- `src/` exists
- `layouts/` exists
- `lang/` exists
- `dist/` is reported only if already present
- `--admin` requires `template.json.type=admin`

## Smoke-Check-Only Command

Run health checks without changing template state:

```bash
/usr/local/bin/php83 artisan system:smoke-check
```

Checks:

- `/`
- `/admin/dashboard`
- `/api/templates/{active_user_template}/routes.json`
- `/api/layouts/{active_admin_template}/admin_dashboard.json`
- `/api/admin/dashboard/resources`

## Emergency Bypass Options

Use bypass options only when the operator already understands the failure and accepts the risk.

Skip preflight:

```bash
/usr/local/bin/php83 artisan template:release {identifier} --skip-preflight
```

Skip smoke-check:

```bash
/usr/local/bin/php83 artisan template:release {identifier} --skip-smoke
```

Related internal safeguards:

- `template:update` runs smoke-check automatically after a successful update
- `template:activate` runs smoke-check automatically after a successful activation
- `template:release` suppresses nested auto smoke-check runs to avoid duplication

## Diagnostic Log Location

Primary structured diagnostics:

```text
storage/logs/extension-load-diagnostics.log
```

Laravel application log:

```text
storage/logs/laravel.log
```

## What To Check When Release Fails

Check the step shown in the release summary table first.

- If `preflight` fails, inspect missing files, invalid JSON, or wrong template type under `templates/_bundled/{identifier}`.
- If `build` fails, inspect the template source and frontend build output under `templates/_bundled/{identifier}`.
- If `update` fails, inspect active template state, file permissions, and update output.
- If `activate` fails, inspect template type, active state, and dependency requirements.
- If cache clear steps fail, inspect runtime permissions for `storage/` and `bootstrap/cache/`.
- Read `storage/logs/extension-load-diagnostics.log` for structured events.
- Read `storage/logs/laravel.log` for application exceptions.

## What To Check When Smoke-Check Fails

- Identify which route failed and whether the failure is status-based or JSON-based.
- Check active user and admin templates.
- Check `/api/templates/{active_user_template}/routes.json`.
- Check `/api/layouts/{active_admin_template}/admin_dashboard.json`.
- Check `/api/admin/dashboard/resources` for auth or runtime metric failures.
- Read `storage/logs/extension-load-diagnostics.log` for `smoke-check.http` events.
- Read `storage/logs/laravel.log` if the failing route produced an application exception.
- If the failing path depends on a recent template update, compare `_bundled` and active `templates/{identifier}` contents.

## Safe Rollback Note

Rollback is not automated.

Until release history and reproducible rollback metadata exist, rollback should be manual. Operators should restore the previous known-good template state through the existing update and activation workflow, plus any required file backup or VCS-based recovery procedure.

## Operator Checklist

1. Confirm the target identifier and whether it is a user or admin template.
2. Run preflight.
3. Review preflight output before continuing.
4. Run the standard release command.
5. Review the release summary table.
6. Confirm smoke-check passed.
7. If release fails, inspect the failing step and both log files.
8. If smoke-check fails, inspect the exact failing route and active template state.
9. Do not bypass preflight or smoke-check unless the reason is documented.
10. Do not perform rollback automatically. Use a manual recovery procedure if needed.
