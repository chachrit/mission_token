<?php
/**
 * deploy.php — Remote Git Pull trigger
 * Call: POST http://yourserver/mission_token/deploy.php
 *       with header X-Deploy-Token: <DEPLOY_SECRET>
 *
 * DELETE or restrict access to this file in production when not in use.
 */

// ── Secret key — change this to something random ──────────
define('DEPLOY_SECRET', 'deploy_mt_2026_J4k9mPqW');

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

// IIS AppPool can't write global gitconfig — write a local one instead
$safeCfg = $projectDir . DIRECTORY_SEPARATOR . '.gitconfig_deploy';
file_put_contents($safeCfg, "[safe]\n\tdirectory = " . str_replace('\\', '/', $projectDir) . "\n");
putenv('GIT_CONFIG_GLOBAL=' . $safeCfg);

exec('git -C ' . escapeshellarg($projectDir) . ' fetch --all 2>&1', $output, $return);
exec('git -C ' . escapeshellarg($projectDir) . ' reset --hard origin/main 2>&1', $output, $return);

// Clean up temp config
@unlink($safeCfg);

header('Content-Type: application/json');
echo json_encode([
    'ok'        => $return === 0,
    'time'      => date('Y-m-d H:i:s'),
    'returnCode'=> $return,
    'output'    => implode("\n", $output),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
