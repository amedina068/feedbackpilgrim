## Summary

<!-- Say what the change does and why. Link the issue, for example "Closes #12". -->

## Release note

<!-- If users can see the change, write one or two short sentences in plain text. The maintainer copies them to the release notes in the docs repository. If users cannot see the change, remove this section. -->

## Checks

- [ ] `php artisan test` passes.
- [ ] `vendor/bin/pint --test` passes.
- [ ] `npm run build` passes (when the front-end or the widget changes).
- [ ] `npm run test:js` passes (when the widget changes).
- [ ] `php artisan migrate:fresh --seed` passes (when a migration changes).
- [ ] `CHANGELOG.md` has a line under `[Unreleased]` (when the behavior for users changes).
- [ ] The PR title follows Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:` and similar).

## Compatibility

- [ ] The change has no database, configuration or public API change, or the change is described below.
- [ ] The REST API, the widget (`pilgrim.js`) and the MCP tools keep their contract, or the change is described below.
- [ ] ARCHITECTURE.md and DESIGN_SYSTEM.md are current.
- [ ] A screenshot or a terminal output is attached for a visible change.

<!-- Describe the migration, the new environment values, the API change and how to roll back. -->
