<?php
/**
 * deploy.php — Remote Git Pull trigger
 * Call: POST http://yourserver/mission_token/deploy.php
 *       with header X-Deploy-Token: <DEPLOY_SECRET>
 *
 * DELETE or restrict access to this file in production when not in use.
 */

// ── Load secret key from secrets.php ──────────────────────
require_once __DIR__ . '/config/secrets.php';

// ── Auth check ─────────────────────────────────────────────
$token = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ($_GET['token'] ?? '');
if (!hash_equals(DEPLOY_SECRET, $token)) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Unauthorized']));
}

// ── Run git pull ───────────────────────────────────────────
$projectDir = realpath(__DIR__);
$safeDir    = str_replace('\\', '/', $projectDir);
$output = [];
$return = 0;

// Pass safe.directory inline via -c flag (bypasses global config permission issues on IIS)
$gitBase = 'git -C ' . escapeshellarg($projectDir)
         . ' -c safe.directory=' . escapeshellarg($safeDir);

exec($gitBase . ' fetch --all 2>&1', $output, $return);
exec($gitBase . ' reset --hard origin/main 2>&1', $output, $return2);
$return = max($return, $return2);

header('Content-Type: application/json');
echo json_encode([
    'ok'        => $return === 0,
    'time'      => date('Y-m-d H:i:s'),
    'returnCode'=> $return,
    'output'    => implode("\n", $output),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
