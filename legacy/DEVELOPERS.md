# Developer Guide

Conventions, architecture, and workflow for contributors.

## Architecture

Laravel app, using Filament, Livewire, Tailwind...

**FOCUS ON FEATURES**: make proper use of Filament, Vite, Livewire, Tailwind and any other Laravel extension, to restrict the developement to features, not wasting time on layout and styling.

- Make the most of native app features. Strictly reject creating custom features already provided by native Laravel and extensions.

- **all css** must be set in .css files in resources/css/
- **Never** use direct styling in templates
- **never add** inline styling in html templates
- **Only** use worker classes, prefer native classes, keep native styling, no specific css rules unless absolutely necessary
- **all js** must be set in .jss files in resources/js/
- **never add** inline scripts in html templates
- **use a main app template** and call it with @extend from specific page views
- **use Filament for all forms**, do not create custom form views

## Project layout

```
2do-aggregator
├── app						# Standard Laravel folder structure
├── bootstrap
├── config
├── database
├── public
├── resources
├── routes
├── storage
├── tests
├── vendor
├── dev						# Our additional folders
├── tmp
├── bundle
│   │   ├── currency.php	  
│   ├── helpers				# standalone helpers
│   │   │   				# https://github.com/GuduleLapointe/opensim-helpers/tree/2.x
│   │   ├── events.php
│   │   ├── guide.php
│   │   ├── index.php
│   │   ├── landtool.php
│   │   ├── offline.php
│   │   ├── parser.php
│   │   ├── query.php
│   │   ├── register.php
│   │   └── ...
│   ├── lsl					# LSL scripts folder, on top level to be easily findable
│   │   ├── 2do-board		# https://github.com/GuduleLapointe/2do-board/tree/dev
│   │   └── ...
│   └── ...
└── ...
```

## Git conventions

### Branches
- Work on `dev`. `master` is stable and public.
- Only the project owner merges `dev → master` and pushes.

### Commit message format

```
(scope) short subject

- detail
- detail

Optional additional context.
```

- **scope**: area of the change — e.g. `(api)`, `(lsl)`, `(build)`, `(tests)`, `(doc)`, `(config)`
- **subject**: imperative, lowercase, no trailing period
- **details**: bullet list with `-`, one item per logical change
- Omit details for trivial single-change commits
- Prefix with `(untested)` when the change has not been verified yet; reword after a successful test

### Version releases

```
v1.2.3 Main change if applicable
- new ...
- new ...
- fix ...
- update ...
```

- **subject** first line begins exactly with "v" + the version number to allow automated workflows and maintenance scripts. An option description of the main change might be added if relevant
- **details** a list of the main changes since the previous version release commit
- create a version release only when the version is fully tested and approved: bumping the version number in files does not mean the version must be released yet
- Be concise, full explanation can be found in git history
- Omit small patches and fixes, focus on essential features
- Make sure to update all relevant files (.version, README.md, composer.json... ) and update CHANGELOG.md with the exact same description
- after commit, add a tag with "v1.2.3" (version number) as tag name and the exact same message as commit

## Testing

```bash
./tests/run-tests.sh
```

Requirements tests (`tests/Requirements/`) need a running dev server.
Start it with `dev/start-server.sh` before running the full suite.

Unit tests and feature tests run without a server.

See `tests/README.md` for details.

## Code style

- PHP 8.2+, checked by `phpcs` with PHPCompatibility.
- English for all code, comments, and documentation.
- Prefer simple, well-tested constructs. Avoid global mutable state.
- Reuse existing libraries and patterns already in the project.
- Small, focused unit tests for new logic.
