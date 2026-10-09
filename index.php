<?php
/**
 * Sacred Heart College (Autonomous), Tirupattur
 * Smart Student Movement & Digital Gate Pass Management System
 *
 * Single entry point. Every request is routed from here.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/Csrf.php';
require_once __DIR__ . '/core/Logger.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Model.php';
require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/core/Barcode.php';
require_once __DIR__ . '/core/Router.php';

Auth::start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

(new Router())->dispatch($_GET['url'] ?? '');
