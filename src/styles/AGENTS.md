# AGENTS.md

Flat PHP web app (no framework, no Composer) for German↔Turkish vocabulary
flashcards. Runs in the XAMPP docroot; each `.php` file is a page served
directly by Apache.

## Run / verify
- Serve via XAMPP: place in `htdocs/deutsch`, open `http://localhost/deutsch/index.php`.
  No build or install step. There are no tests, lint, or typecheck commands.
- Login is required for `dashboard.php` and `game.php` (via `$_SESSION['user_id']`).
  Default seeded account: `admin` / `admin123` (created in `db.php` if `users` is empty).

## Database & schema (do not hand-edit data.sqlite)
- `db.php` returns a singleton PDO to `data.sqlite` and is required by every page
  (it also calls `session_start()`).
- Schema is created/migrated automatically on first `db()` call:
  `CREATE TABLE IF NOT EXISTS` in `db.php` + `migrateSchema()`. Add columns there,
  not by editing the DB file.
- `.htaccess` denies direct web access to `*.sqlite`. `data.sqlite` is git-managed
  and is auto-created at runtime, so don't add `.sqlite` paths as a step.

## Adding / editing vocabulary
- Word data lives in `wordlists/*.xml`, NOT in the DB. Files are seeded into the
  `words` table automatically on page load, tracked by filename + md5 hash in
  `seeded_files`. Change an XML file and the next request re-imports it.
- XML format: root `<wortschatz>`, `<kategorie name="...">` groups, each
  `<eintrag>` holds `<deutsch>`, `<tuerkisch>`, and optional `<satz>` with
  `<deutsch>`/`<tuerkisch>` example sentences. Skip empty entries; drop a file to
  remove its words.
- Avoid duplicate `<deutsch>` values: `deutsch` is UNIQUE. `importWordlist()` uses
  `INSERT OR IGNORE` and `ON CONFLICT(deutsch)` upserts; sentences on the same word
  overwrite, plain entries don't.

## Codebase map
- `index.php` home · `login.php` session auth · `dashboard.php` logged-in home ·
  `game.php` flashcards (loads all `words`, shuffled, filterable by category;
  sentences toggle persisted in `localStorage`) · `logout.php` · `style.css`. All
  output pages are small inline-PHP templates (no separate view layer).
- `game.php` embeds card data as JSON (`JSON_UNESCAPED_UNICODE`), so key escaping
  matters for the `<script>` block.

## Conventions / gotchas
- `db.php` defines `declare(strict_types=1)` but the page scripts rely on inline
  HTML templates using short echo (`<?= ?>`) and HTML-escaping via
  `htmlspecialchars()` — follow that for user/session-derived output.
- `wordlists/` contains both a `wordlist.xml` (base vocab) and
  `wordlist-sentences.xml` (same words plus `<satz>`); expect overlapping entries,
  which is intentional given the upsert. Don't "fix" these as duplicates.