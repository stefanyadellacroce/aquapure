<?php
declare(strict_types=1);

const APP_NAME = 'AquaPure Water Refilling Station';
const APP_TIMEZONE = 'Asia/Manila';

date_default_timezone_set(APP_TIMEZONE);

/**
 * Change AQUAPURE_BASE_URL when the folder name is not "aquapure".
 * Example in Apache: SetEnv AQUAPURE_BASE_URL /my-water-station
 */
define('BASE_URL', rtrim(getenv('AQUAPURE_BASE_URL') ?: '/aquapure', '/'));

/**
 * Public absolute URL used by optional hosted payment return links.
 * Use HTTPS with live payment keys.
 */
define(
    'APP_URL',
    rtrim(getenv('AQUAPURE_APP_URL') ?: 'http://localhost' . BASE_URL, '/'),
);
