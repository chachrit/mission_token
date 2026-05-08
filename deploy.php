<?php
/**
 * deploy.php — Remote Git Pull trigger
 * Call: POST http://yourserver/mission_token/deploy.php
 *       with header X-Deploy-Token: <DEPLOY_SECRET>
 *
 * DELETE or restrict access to this file in production when not in use.
 */

// ── Secret key — change this to something random ──────────
define('DEPLOY_SECRET', 'OHERbnJ9ClkUsWfM7vmXFtBQ8w36Tryp');

// ── Auth check ─────────────────────────────────────────────
$token = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ($_GET['token'] ?? '');
if (!hash_equals(DEPLOY_SECRET, $token)) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Unauthorized']));
}

// ── Run git pull ───────────────────────────────────────────
$projectDir = __DIR__;
$output = [];
$return = 0;

exec('git -C ' . escapeshellarg($projectDir) . ' fetch --all 2>&1', $output, $return);
exec('git -C ' . escapeshellarg($projectDir) . ' reset --hard origin/main 2>&1', $output, $return);

header('Content-Type: application/json');
echo json_encode([
    'ok'        => $return === 0,
    'time'      => date('Y-m-d H:i:s'),
    'returnCode'=> $return,
    'output'    => implode("\n", $output),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
