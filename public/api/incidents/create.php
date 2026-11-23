<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/database.php';

requireMethod('POST');
requireApiKey();

$type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]));
if ($type !== 'application/json') {
    respond(415, ['error' => 'json_required']);
}
$raw = file_get_contents('php://input', false, null, 0, MAX_REQUEST_LENGTH + 1);
if ($raw === false) {
    respond(400, ['error' => 'invalid_body']);
}
if (strlen($raw) > MAX_REQUEST_LENGTH) {
    respond(413, ['error' => 'body_too_large']);
}
try {
    $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    respond(400, ['error' => 'invalid_json']);
}
if (!is_array($body)) {
    respond(400, ['error' => 'invalid_report']);
}

$reference = $body['reference'] ?? null;
$category = $body['category'] ?? null;
$location = boundedText($body['location'] ?? null, 3, 160);
$details = boundedText($body['details'] ?? null, 10, 1500);
$latitude = $body['latitude'] ?? null;
$longitude = $body['longitude'] ?? null;
$photo = $body['photo'] ?? null;
$coordinatesAreValid = ($latitude === null && $longitude === null)
    || (validCoordinate($latitude, 90) && validCoordinate($longitude, 180));

if (($body['test'] ?? false) !== true || !validReference($reference)
    || !in_array($category, INCIDENT_TYPES, true) || $location === null || $details === null
    || !$coordinatesAreValid || ($photo !== null && !validPhoto($photo))) {
    respond(422, ['error' => 'invalid_report']);
}

try {
    $db = connectDatabase();
    $insert = $db->prepare(<<<'SQL'
        INSERT INTO incident_reports (reference, category, location, details, position, photo_base64)
        VALUES (:reference, :category, :location, :details,
            ST_SetSRID(ST_MakePoint(CAST(:longitude AS double precision), CAST(:latitude AS double precision)), 4326),
            :photo)
        ON CONFLICT (reference) DO NOTHING
        RETURNING reference
        SQL);
    $insert->execute([
        'reference' => strtolower($reference),
        'category' => $category,
        'location' => $location,
        'details' => $details,
        'longitude' => $longitude,
        'latitude' => $latitude,
        'photo' => $photo,
    ]);
    $created = $insert->fetchColumn() !== false;
    if (!$created) {
        $lookup = $db->prepare('SELECT ' . incidentFields() . ', photo_base64 FROM incident_reports WHERE reference = :reference');
        $lookup->execute(['reference' => $reference]);
        $old = $lookup->fetch();
        if (!$old || $old['category'] !== $category || $old['location'] !== $location || $old['details'] !== $details
            || $old['photo_base64'] !== $photo
            || ($old['latitude'] === null ? $latitude !== null : $latitude === null || (float) $old['latitude'] !== (float) $latitude)
            || ($old['longitude'] === null ? $longitude !== null : $longitude === null || (float) $old['longitude'] !== (float) $longitude)) {
            respond(409, ['error' => 'reference_already_used']);
        }
    }
    respond($created ? 201 : 200, ['reference' => strtolower($reference), 'test' => true]);
} catch (Throwable $error) {
    databaseError($error);
}
