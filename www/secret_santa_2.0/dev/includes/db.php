<?php
// ============================================================
// db.php
// Returns a shared PDO connection. Call getDB() anywhere you
// need database access -- it creates the connection once and
// reuses it for the rest of the request (singleton pattern).
//
// USAGE: $pdo = getDB();
// ============================================================

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (IS_DEV) {
                die('Database connection failed: ' . $e->getMessage());
            } else {
                error_log('DB connection failed: ' . $e->getMessage());
                die('A database error occurred. Please try again later.');
            }
        }
    }

    return $pdo;
}