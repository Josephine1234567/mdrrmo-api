<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/database.php';

requireMethod('GET');
requireApiKey();

$reference = $_GET['id'] ?? null;
if (!validReference($reference)) {
    respond(400, ['error' => 'invalid_reference']);
}
try {
    $db = connectDatabase();
    $includePhoto = ($_GET['include_photo'] ?? '') === '1';
    $fields = incidentFields() . ($includePhoto ? ', photo_base64' : '');
    $lookup = $db->prepare('SELECT ' . $fields . ' FROM incident_reports WHERE reference = :reference');
    $lookup->execute(['reference' => $reference]);
    $report = $lookup->fetch();
    if ($report === false) {
        respond(404, ['error' => 'report_not_found']);
    }
    respond(200, ['report' => $report]);
} catch (Throwable $error) {
    databaseError($error);
}
