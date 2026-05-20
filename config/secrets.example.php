<?php
/**
 * config/secrets.example.php
 * Template for secrets.php — commit this file, NOT secrets.php.
 * Copy to config/secrets.php and fill in real values before running the app.
 */

define('EMP_API_KEY', 'your-emp-api-key-here');

// Strava OAuth credentials (https://www.strava.com/settings/api)
define('STRAVA_CLIENT_ID',     'your-strava-client-id');
define('STRAVA_CLIENT_SECRET', 'your-strava-client-secret');

// Deploy webhook secret — used by deploy.php to authenticate git pull triggers
define('DEPLOY_SECRET', 'change-this-to-a-random-string');
