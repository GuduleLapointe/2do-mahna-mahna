# Test suite

Tests are written with [Pest 2](https://pestphp.com/docs/) on top of PHPUnit 10.

## Running tests

`tests/run-tests.sh` and `vendor/bin/pest` should be useable interchangeabily.

```bash
./tests/run-tests.sh          # full suite (requires dev server)
vendor/bin/pest               # same, without server check
vendor/bin/pest --testsuite Requirements
vendor/bin/pest --testsuite Features,Requirements
vendor/bin/pest tests/Requirements/ProjectStructureTest.php
```

**Never** pass the `tests/` directory as a positional argument to Pest — it forces alphabetical ordering and ignores the suite order declared in `phpunit.xml`.

## Suite order

Defined in `phpunit.xml`: **Requirements → Units → Features**.

- **Requirements/** — low-level prerequisites (environment, external deps, project structure, foundational API contracts). Other suites gate on these with `requires()`.
- **Units/** — isolated class/function tests, no external deps.
- **Features/** — end-to-end flows (aggregator, build, deploy, compatibility). May depend on Requirements having passed.

File execution order within each suite is alphabetical. Name files so prerequisites come first when order matters.

## Global test environment (`bootstrap.php` + `Pest.php`)

`tests/bootstrap.php` runs **once** before the entire test run (PHPUnit bootstrap). It defines:

| Constant | Value |
|---|---|
| `APP_DIR` | Project root (from `bootstrap.php`) |
| `TEST_URL` | Dev server URL (from `tests/.env`) |
| `APP_VERSION` | From `.version` file |
| `BOARD_VER` | From `tests/.env` |
| `TEST_DIRECTORY` | Temporary directory for this test run |
| `TEST_DATA_DIR` | `TEST_DIRECTORY/data` — aggregator output |
| `TEST_BUILD_DIR` | `TEST_DIRECTORY/build` — build output |

`TEST_DIRECTORY` and its subdirectories are created once in `bootstrap.php` and deleted at process end via `register_shutdown_function`. **Never** create or delete test directories inside test files.

`tests/Pest.php` configures global hooks via `uses()->in(__DIR__)`:

- `beforeAll` / `afterAll` — log test class start/end (runs once per test file).
- `beforeEach` / `afterEach` — log individual test start/end.

Use `uses()` in `Pest.php` for suite-wide hooks. `beforeAll`/`afterAll` are global and defined excusively in Pest.php through `use()`.

## Dependency system

### Cross-file dependencies: `passed()` and `requires()`

Use these to declare dependencies across test files (e.g. Features depending on Requirements). Tests inside the same group or test file use the regular inline `->depends()` fixture (see below).

```php
// In the test that validates the prerequisite:
passed("Deploy targets");

// In a describe that depends on it:
describe("Cron", function () {
    beforeEach(function () {
        requires("Deploy targets");
    });
    // ...
});
```

`requires()` belongs in `beforeEach`, never inline inside a test body — unless the dependency is unique to one test within a describe that has a different common requirement.

### Within-describe dependencies: `->depends()`

Use Pest's native `->depends("test name")` for sequential tests within the same `describe`. Return values from the parent test are received as parameters by the dependent test.

```php
test("execute bin/aggregator.php", function () {
    exec("php " . APP_DIR . "/bin/aggregator.php " . TEST_DATA_DIR . " 2>&1", $out, $code);
    expect($code)->toBe(0, "Aggregator failed — check logs");
    passed("Aggregator");
});

test("events.json is valid JSON", function () {
    expect(TEST_DATA_DIR . "/events.json")->toBeFile()->toBeReadableFile();
    // ...
})->depends("execute bin/aggregator.php");
```

## Code conventions

### Use constants — never redefine them

`APP_DIR`, `TEST_DATA_DIR`, `TEST_BUILD_DIR`, `TEST_URL` are available everywhere. Never define `$appDir = dirname(__DIR__, 2)` or similar in test files.

### Execution inside tests

Long-running commands (`exec`, file generation) belong **inside** the test body, not at file level. File-level code runs at collection time, before any output, causing silent delays.

### Use native Pest expectations

Prefer Pest's semantic expectations over raw PHP assertions wrapped in `toBeTrue()`:

```php
// Good
expect(APP_DIR . "/data")->toBeDirectory();
expect(APP_DIR . "/data/events.json")->toBeFile()->toBeReadableFile();
expect($content)->toStartWith("BEGIN:VCALENDAR")->toContain("END:VCALENDAR");

// Avoid
expect(is_dir(APP_DIR . "/data"))->toBeTrue();
expect(file_exists(APP_DIR . "/data/events.json"))->toBeTrue();
```

### Error messages: plain text, no variables

Failure messages must be plain strings. Variables are not always expanded in Pest's output and make messages harder to read.

```php
// Good
expect($code)->toBe(0, "Parsed line contains wrong time format");

// Avoid
expect($code)->toBe(0, "Line {$n} parsing failed with error {$error}");
```

### Code style

Use `function () { }` for test bodies. Arrow functions (`fn() =>`) are only acceptable for trivial single-expression closures on one line where they genuinely improve readability — use judgment, and follow the style of surrounding tests.

```php
// Good
test("events.json", function () {
    expect(TEST_DATA_DIR . "/events.json")->toBeFile()->toBeReadableFile();
})->depends("execute bin/aggregator.php");

// Avoid
test("events.json", fn() => expect(TEST_DATA_DIR . "/events.json")->toBeFile())
    ->depends("execute bin/aggregator.php");
```

### Descriptive test names

Test names should describe the action or the thing being verified, not the expected outcome.

```php
// Good
test("execute bin/aggregator.php", ...);
test("events.json is valid JSON", ...);
test("config/targets", ...);

// Avoid — could apply to any test
test("runs without error", ...);
test("check file exists", ...);
```

### Describe

Use `describe` for related tests. Nested `describe` blocks are allowed only when a subset of tests shares a `beforeEach` requirement that differs from the outer describe.

### Don't duplicate tests across suites

If something is verified in `Requirements/`, don't re-check it in `Features/`. Gate on it with `requires()` instead. A check promoted to `Requirements/` means other tests can depend on it — that is the purpose of that suite.

## phpcs false positives

When phpcs flags valid code that only works because of Pest's runtime closure binding (e.g. `static::class` in `beforeAll`), suppress with a targeted inline comment:

```php
testNotice("Start " . static::class); // phpcs:ignore PHPCompatibility.Classes.NewLateStaticBinding
```

Always use the most specific sniff name available, not `// phpcs:ignore` alone.
