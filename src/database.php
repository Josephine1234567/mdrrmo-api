<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

const MAX_PHOTO_LENGTH = 400000;
const MAX_REQUEST_LENGTH = 440000;
const INCIDENT_TYPES = ['flood', 'landslide', 'fire', 'earthquake', 'road', 'other'];

function initializeDatabase(PDO $db): void
{
    $db->beginTransaction();
    try {
        $db->exec('SELECT pg_advisory_xact_lock(4831062308)');
        $db->exec('CREATE EXTENSION IF NOT EXISTS postgis');
        $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS incident_reports (
            reference UUID PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            category TEXT NOT NULL CHECK (category IN ('flood', 'landslide', 'fire', 'earthquake', 'road', 'other')),
            location TEXT NOT NULL CHECK (char_length(location) BETWEEN 3 AND 160),
            details TEXT NOT NULL CHECK (char_length(details) BETWEEN 10 AND 1500),
            position GEOMETRY(Point, 4326),
            photo_base64 TEXT CHECK (char_length(photo_base64) <= 400000),
            CHECK (position IS NULL OR (ST_X(position) BETWEEN -180 AND 180 AND ST_Y(position) BETWEEN -90 AND 90))
        )
        SQL);
        $db->exec('CREATE INDEX IF NOT EXISTS incident_reports_created_at_idx ON incident_reports (created_at DESC, reference DESC)');
        $db->exec('CREATE INDEX IF NOT EXISTS incident_reports_position_idx ON incident_reports USING GIST (position)');
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function respond(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        respond(405, ['error' => 'method_not_allowed']);
    }
}

function requireApiKey(): void
{
    $expected = getenv('API_KEY');
    if ($expected === false || strlen($expected) < 24) {
        respond(503, ['error' => 'api_not_configured']);
    }
    $provided = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($provided, 'Bearer ') || !hash_equals($expected, substr($provided, 7))) {
        header('WWW-Authenticate: Bearer');
        respond(401, ['error' => 'unauthorized']);
    }
}

function validReference(mixed $value): bool
{
    return is_string($value) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) === 1;
}

function boundedText(mixed $value, int $min, int $max): ?string
{
    if (!is_string($value) || strlen($value) > $max * 4 || str_contains($value, "\0")) {
        return null;
    }
    $value = trim($value);
    $length = preg_match_all('/./us', $value);
    return $length !== false && $length >= $min && $length <= $max ? $value : null;
}

function validCoordinate(mixed $value, float $max): bool
{
    return (is_int($value) || is_float($value)) && is_finite((float) $value) && abs($value) <= $max;
}

function validPhoto(mixed $photo): bool
{
    if (!is_string($photo) || strlen($photo) > MAX_PHOTO_LENGTH) {
        return false;
    }
    $bytes = base64_decode($photo, true);
    if ($bytes === false || base64_encode($bytes) !== $photo) {
        return false;
    }
    $size = @getimagesizefromstring($bytes);
    return $size !== false && $size[2] === IMAGETYPE_JPEG
        && $size[0] > 0 && $size[0] <= 1200 && $size[1] > 0 && $size[1] <= 1200;
}

function incidentFields(): string
{
    return 'reference, created_at, category, location, details, ST_Y(position) AS latitude, '
        . 'ST_X(position) AS longitude, (photo_base64 IS NOT NULL) AS has_photo';
}

function databaseError(Throwable $error): never
{
    error_log('MDRRMO database request failed (' . get_class($error) . ', code ' . $error->getCode() . ')');
    respond(503, ['error' => 'database_unavailable']);
}
