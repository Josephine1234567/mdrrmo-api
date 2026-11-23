<?php
declare(strict_types=1);

require_once __DIR__ . '/src/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $db = connectDatabase();
    if (($argv[1] ?? '') === '--init') {
        initializeDatabase($db);
    }
    $version = $db->query('SELECT PostGIS_Version()')->fetchColumn();
    echo "PostgreSQL connection OK. PostGIS: $version" . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Database check failed. Verify DATABASE_URL, database access, and permission to enable PostGIS. '
        . 'Error code: ' . $error->getCode() . PHP_EOL);
    exit(1);
}
