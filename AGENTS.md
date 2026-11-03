# OpenPanel Community package

This repository contains a Laravel package, not a Laravel application. Do not add a root artisan, app, bootstrap, public, storage, .env.example, host routes, host configuration, or framework skeleton. Laravel for the server is created inside the Docker image; Laravel for tests comes from Orchestra Testbench.

## Structure and compatibility

- Package PHP code belongs in src with the QOpenPanel\Community namespace.
- Preserve the isolated qopenpanel guard, configuration prefix, route names and database table prefix. Host authentication, routes, translations and sessions must remain independent.
- Register routes, commands, migrations, views and assets through QOpenPanelServiceProvider.
- Check composer.json, composer.lock and package.json before relying on dependency APIs. Current compatibility starts at Laravel 13/Inertia 3; the image uses PHP 8.5.
- Do not change dependencies outside the requested task. Never edit vendor files.
- Preserve bundled template versions and checksums. New content requires a new version.
- Credential values must never appear in MCP responses, logs, generated manifests, documentation or commits.

## Development and validation

- Use rg for repository searches and follow nearby code conventions.
- Run composer test for PHP feature tests. Testbench creates an isolated host; do not recreate a root application for tests.
- Run php vendor/bin/testbench for package Artisan commands, such as panel:templates-check.
- Add meaningful regression tests for changed behavior, tenant boundaries and failure modes. Avoid tests that only repeat implementation details or assert true.
- Run php vendor/bin/pint --dirty --format agent after PHP changes.
- Run node --test tests/Unit/*.test.mjs for JavaScript, installer and distribution tests.
- Run npm run translations:check, npm run build:package and npm run build:demo when affected. Commit the built module assets in resources/dist.
- The installer must preserve existing host packages/workloads on conflicts and use official signed package repositories. Never add automatic destructive cleanup or silently grant Docker-group privileges.
- Do not claim real Linux provisioning, physical quotas, or cloud certification where the executor or validation is absent.
