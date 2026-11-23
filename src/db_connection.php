<?php
declare(strict_types=1);

function connectDatabase(): PDO
{
    $url = getenv('DATABASE_URL');
    $parts = $url === false ? false : parse_url($url);
    if (!$parts || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)) {
        throw new RuntimeException('Set DATABASE_URL to a PostgreSQL connection URL.');
    }

    $host = trim($parts['host'] ?? '', '[]');
    $name = rawurldecode(ltrim($parts['path'] ?? '', '/'));
    $port = $parts['port'] ?? 5432;
    if (!preg_match('/^[a-z0-9.:\-]+$/i', $host) || !preg_match('/^[a-z0-9_.-]+$/i', $name)
        || $port < 1 || $port > 65535 || empty($parts['user'])) {
        throw new RuntimeException('DATABASE_URL is missing valid connection fields.');
    }
    parse_str($parts['query'] ?? '', $options);
    $ssl = $options['sslmode'] ?? 'prefer';
    if (!in_array($ssl, ['disable', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
        throw new RuntimeException('DATABASE_URL has an unsupported sslmode.');
    }

    return new PDO(
        "pgsql:host=$host;port=$port;dbname=$name;sslmode=$ssl;connect_timeout=5",
        rawurldecode($parts['user']),
        rawurldecode($parts['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
