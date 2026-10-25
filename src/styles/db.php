<?php
declare(strict_types=1);

session_start();

define('DB_FILE', __DIR__ . '/data.sqlite');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL DEFAULT \'\',
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT \'user\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)');
            $stmt->execute([
                ':u' => 'admin',
                ':p' => password_hash('admin123', PASSWORD_DEFAULT),
                ':r' => 'admin',
            ]);
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS words (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kategorie TEXT NOT NULL,
            deutsch TEXT NOT NULL UNIQUE,
            tuerkisch TEXT NOT NULL,
            markdown TEXT NOT NULL DEFAULT \'\'
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS seeded_files (
            filename TEXT PRIMARY KEY,
            seeded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        migrateSchema($pdo);

        seedWordlists($pdo);
    }

    return $pdo;
}

function migrateSchema(PDO $pdo): void
{
    $userCols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('email', array_column($userCols, 'name'), true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('role', array_column($userCols, 'name'), true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'");
    }

    $pdo->exec("UPDATE users SET role = 'admin' WHERE username = 'admin'");

    $columns = $pdo->query('PRAGMA table_info(words)')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_flip(array_column($columns, 'name'));

    if (!isset($names['satz_deutsch'])) {
        $pdo->exec("ALTER TABLE words ADD COLUMN satz_deutsch TEXT NOT NULL DEFAULT ''");
    }
    if (!isset($names['satz_tuerkisch'])) {
        $pdo->exec("ALTER TABLE words ADD COLUMN satz_tuerkisch TEXT NOT NULL DEFAULT ''");
    }
    if (!isset($names['markdown'])) {
        $pdo->exec("ALTER TABLE words ADD COLUMN markdown TEXT NOT NULL DEFAULT ''");
    }

    $seededCols = $pdo->query('PRAGMA table_info(seeded_files)')->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('hash', array_column($seededCols, 'name'), true)) {
        $pdo->exec('ALTER TABLE seeded_files ADD COLUMN hash TEXT NOT NULL DEFAULT ""');
    }
}

function seedWordlists(PDO $pdo): void
{
    $files = glob(__DIR__ . '/wordlists/*.xml');
    if ($files === false) {
        return;
    }

    $done = [];
    foreach ($pdo->query('SELECT filename, hash FROM seeded_files') as $row) {
        $done[$row['filename']] = $row['hash'];
    }

    $imported = false;
    foreach ($files as $file) {
        $name = basename($file);
        $hash = md5_file($file);
        if (isset($done[$name]) && $done[$name] === $hash) {
            continue;
        }
        importWordlist($pdo, $file);

        $stmt = $pdo->prepare('INSERT INTO seeded_files (filename, hash) VALUES (:f, :h)
            ON CONFLICT(filename) DO UPDATE SET hash = excluded.hash, seeded_at = CURRENT_TIMESTAMP');
        $stmt->execute([':f' => $name, ':h' => $hash]);
        $imported = true;
    }

    if ($imported) {
        $pdo->exec("UPDATE words SET kategorie = 'andere Wörter' WHERE kategorie = 'andere_Woerter'");
    }
}

function importWordlist(PDO $pdo, string $file): void
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        return;
    }

    $labels = ['andere_Woerter' => 'andere Wörter'];

    $insertPlain = $pdo->prepare('INSERT OR IGNORE INTO words (kategorie, deutsch, tuerkisch)
        VALUES (:k, :d, :t)');
    $insertWithSatz = $pdo->prepare('INSERT INTO words (kategorie, deutsch, tuerkisch, satz_deutsch, satz_tuerkisch)
        VALUES (:k, :d, :t, :sd, :st)
        ON CONFLICT(deutsch) DO UPDATE SET
            satz_deutsch = excluded.satz_deutsch,
            satz_tuerkisch = excluded.satz_tuerkisch');

    foreach ($xml->xpath('//kategorie') as $kategorie) {
        $raw  = trim((string)$kategorie['name']);
        $name = $labels[$raw] ?? $raw;
        foreach ($kategorie->eintrag as $eintrag) {
            $deutsch   = trim((string)$eintrag->deutsch);
            $tuerkisch = trim((string)$eintrag->tuerkisch);
            if ($deutsch === '' || $tuerkisch === '') {
                continue;
            }

            $satzDe   = isset($eintrag->satz) ? trim((string)$eintrag->satz->deutsch) : '';
            $satzTr   = isset($eintrag->satz) ? trim((string)$eintrag->satz->tuerkisch) : '';

            if ($satzDe !== '' && $satzTr !== '') {
                $insertWithSatz->execute([
                    ':k' => $name, ':d' => $deutsch, ':t' => $tuerkisch,
                    ':sd' => $satzDe, ':st' => $satzTr,
                ]);
            } else {
                $insertPlain->execute([':k' => $name, ':d' => $deutsch, ':t' => $tuerkisch]);
            }
        }
    }
}
