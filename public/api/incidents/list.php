<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/database.php';

requireMethod('GET');
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/health') {
    try {
        $db = connectDatabase();
        $db->query('SELECT position FROM incident_reports LIMIT 0');
        $db->query('SELECT PostGIS_Version()');
        respond(200, ['status' => 'ok']);
    } catch (Throwable $error) {
        databaseError($error);
    }
}
requireApiKey();

$limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
$offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
if ($limit === false || $offset === false) {
    respond(400, ['error' => 'invalid_pagination']);
}
try {
    $db = connectDatabase();
    $list = $db->prepare('SELECT ' . incidentFields()
        . ' FROM incident_reports ORDER BY created_at DESC, reference DESC LIMIT :count OFFSET :start');
    $list->bindValue(':count', $limit, PDO::PARAM_INT);
    $list->bindValue(':start', $offset, PDO::PARAM_INT);
    $list->execute();
    respond(200, ['reports' => $list->fetchAll()]);
} catch (Throwable $error) {
    databaseError($error);
}
