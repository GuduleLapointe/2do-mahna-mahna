# Unit tests

Small, isolated tests for a single class, function, or helper. No HTTP,
no filesystem side-effects, no external services.

## Conventions

- Run **after** `Requirements/` and alongside `Features/` (declared in
  `phpunit.xml`).
- One file per unit under test (`FooTest.php` for class `Foo`,
  `helpers_xxx_test.php` style is fine for free functions — keep it
  obvious).
- File order inside this folder is alphabetical and should not matter:
  units must be independent.
- No cross-test state. Don't use the `passed/requires` registry here —
  if a unit test needs setup, do it in `beforeEach` within the file.
- Mock or stub external dependencies; reserve real I/O for `Features/`.
