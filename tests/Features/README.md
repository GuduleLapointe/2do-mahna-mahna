# Feature tests

End-to-end behaviour of complex flows: action routes, multi-step
processes, integrations between several components.

## Conventions

- Run **after** `Requirements/` (declared second in `phpunit.xml`).
- Assume requirements have passed; gate on them with
  `requires("Test URL", ...)` in `beforeEach` rather than re-checking.
- One describe per feature, one file per describe. File order inside
  this folder is alphabetical — don't rely on it for correctness.
- Prefer black-box assertions on observable output (HTTP responses,
  rendered images, generated files). Reach for unit-level coverage in
  `Units/` instead when a single class or function is under test.
- If a feature becomes a building block for other features, move its
  test to `Requirements/`.
