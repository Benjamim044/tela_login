<?php

function fail_configuration(string $message): void
{
    http_response_code(500);
    echo '<h1>Configuration error</h1>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

function write_log(string $event, array $context = []): void
{
    $logFile = __DIR__ . '/access.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $path = $_SERVER['REQUEST_URI'] ?? basename($_SERVER['PHP_SELF'] ?? '');
    $payload = $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '{}';

    if ($payload === false) {
        $payload = '{}';
    }

    $entry = sprintf(
        "[%s] event=%s ip=%s method=%s path=%s context=%s%s",
        $timestamp,
        $event,
        $ip,
        $method,
        $path,
        $payload,
        PHP_EOL
    );

    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function open_database(): SQLite3
{
    if (!class_exists('SQLite3')) {
        fail_configuration('The PHP SQLite3 extension is not enabled.');
    }

    $databaseFile = __DIR__ . '/database.sqlite';
    $database = new SQLite3($databaseFile);
    $database->busyTimeout(5000);

    return $database;
}

function initialize_database(SQLite3 $database): void
{
    $tableQuery = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL
)
SQL;

    if (!$database->exec($tableQuery)) {
        fail_configuration('Could not create the users table: ' . $database->lastErrorMsg());
    }

    $userCount = $database->querySingle('SELECT COUNT(*) FROM users');

    if ((int) $userCount > 0) {
        return;
    }

    if (!$database->exec('BEGIN IMMEDIATE TRANSACTION')) {
        fail_configuration('Could not start the database initialization transaction: ' . $database->lastErrorMsg());
    }

    $result = $database->query('SELECT COUNT(*) AS count FROM users');

    if (!$result) {
        $database->exec('ROLLBACK');
        fail_configuration('Could not inspect the users table: ' . $database->lastErrorMsg());
    }

    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row && (int) $row['count'] === 0) {
        $statement = $database->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');

        if (!$statement) {
            $database->exec('ROLLBACK');
            fail_configuration('Could not prepare the default user insert: ' . $database->lastErrorMsg());
        }

        $statement->bindValue(':email', 'admin@example.com', SQLITE3_TEXT);
        $statement->bindValue(':password', password_hash('123456', PASSWORD_DEFAULT), SQLITE3_TEXT);

        if (!$statement->execute()) {
            $database->exec('ROLLBACK');
            fail_configuration('Could not insert the default user: ' . $database->lastErrorMsg());
        }
    }

    if (!$database->exec('COMMIT')) {
        fail_configuration('Could not finish the database initialization transaction: ' . $database->lastErrorMsg());
    }
}

function find_user_by_id(SQLite3 $database, int $userId): ?array
{
    $statement = $database->prepare('SELECT id, email, password FROM users WHERE id = :id');

    if (!$statement) {
        write_log('database_prepare_failed', ['operation' => 'find_user_by_id', 'user_id' => $userId]);
        return null;
    }

    $statement->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $statement->execute();

    if (!$result) {
        write_log('database_query_failed', ['operation' => 'find_user_by_id', 'user_id' => $userId]);
        return null;
    }

    $user = $result->fetchArray(SQLITE3_ASSOC);

    return $user ?: null;
}

function find_user_by_email(SQLite3 $database, string $email): ?array
{
    $statement = $database->prepare('SELECT id, email, password FROM users WHERE email = :email');

    if (!$statement) {
        write_log('database_prepare_failed', ['operation' => 'find_user_by_email', 'email' => $email]);
        return null;
    }

    $statement->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $statement->execute();

    if (!$result) {
        write_log('database_query_failed', ['operation' => 'find_user_by_email', 'email' => $email]);
        return null;
    }

    $user = $result->fetchArray(SQLITE3_ASSOC);

    return $user ?: null;
}

function create_user(SQLite3 $database, string $email, string $password): ?array
{
    $statement = $database->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');

    if (!$statement) {
        write_log('database_prepare_failed', ['operation' => 'create_user', 'email' => $email]);
        return null;
    }

    $statement->bindValue(':email', $email, SQLITE3_TEXT);
    $statement->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
    $result = $statement->execute();

    if (!$result) {
        write_log('database_query_failed', [
            'operation' => 'create_user',
            'email' => $email,
            'error' => $database->lastErrorMsg(),
        ]);
        return null;
    }

    $userId = $database->lastInsertRowID();

    return [
        'id' => $userId,
        'email' => $email,
    ];
}

$db = open_database();
initialize_database($db);
