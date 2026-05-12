<?php
// test.php — deployment test page
// DELETE this file after confirming deploy works
echo json_encode([
    'ok'      => true,
    'message' => 'Deploy สำเร็จ!',
    'time'    => date('Y-m-d H:i:s'),
    'php'     => PHP_VERSION,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
