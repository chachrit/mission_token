<?php
/**
 * config/strava.php
 * Strava API OAuth 2.0 credentials
 *
 * Setup:
 * 1. Go to https://www.strava.com/settings/api
 * 2. Create an application, set "Authorization Callback Domain" to: localhost
 * 3. Fill in Client ID and Client Secret below
 */

define('STRAVA_CLIENT_ID',     '236444');            // ← replace with your Client ID
define('STRAVA_CLIENT_SECRET', 'c6fc3306476fdc9896fc58fb26d0ddcef0482861');   // ← replace with your Client Secret

define('STRAVA_REDIRECT_URI',  BASE_URL . '/strava_callback.php');
define('STRAVA_API_BASE',      'https://www.strava.com/api/v3');
define('STRAVA_AUTH_URL',      'https://www.strava.com/oauth/authorize');
define('STRAVA_TOKEN_URL',     'https://www.strava.com/api/v3/oauth/token');
define('STRAVA_SCOPE',         'activity:read_all');
