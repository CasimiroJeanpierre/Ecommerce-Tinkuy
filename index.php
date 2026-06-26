<?php
// Fallback entry point when DocumentRoot is wwwroot (startup.sh not yet applied).
// Forces SCRIPT_NAME so URL base is '/' instead of '/public'.
$_SERVER['SCRIPT_NAME'] = '/index.php';
require_once __DIR__ . '/public/index.php';
