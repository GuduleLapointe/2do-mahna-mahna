# Requirements tests

Low-level prerequisites the rest of the suite relies on: environment
configuration, external dependencies, foundational HTTP endpoints, and
contracts other features will build on.

## Conventions

- Run **before** `Features/` and `Units/` (declared first in
  `phpunit.xml`).
- Use `passed("name")` once a check succeeds and `requires("name")` in
  dependent describes' `beforeEach` to skip cleanly when a prerequisite
  failed (cross-file dependencies — Pest's `->depends()` is scoped to a
  single describe).
- Within a single describe, chain related tests with
  `->depends("previous test")` and pass values through `return` /
  function arguments to avoid re-fetching.
- Keep one describe per file. File order inside this folder is
  alphabetical — name files so that prerequisites come first
  (`EnvironmentTest.php` before `V2ApiTest.php`, etc.).
- Promote a test here when something else starts depending on it.
