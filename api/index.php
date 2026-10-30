<?php

declare(strict_types=1);

/**
 * Vercel Serverless Function entry point for Laravel.
 *
 * This file adapts incoming Vercel Serverless Function requests to Laravel's
 * public/index.php, ensuring that:
 * 1. Routing SCRIPT_NAME is normalized so Laravel doesn't prefix URLs with /api.
 * 2. Writable directories in /tmp are generated for runtime storage, logs, and views.
 * 3. Environment overrides redirect cache and views to /tmp (preventing read-only filesystem errors).
 */

$_SERVER['SCRIPT_NAME'] = '/index.php';

// Ephemeral directories required by Laravel in Vercel's serverless environment (/tmp)
$storageDirs = [
    '/tmp/storage',
    '/tmp/storage/app',
    '/tmp/storage/app/public',
    '/tmp/storage/framework',
    '/tmp/storage/framework/cache',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
    '/tmp/views',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Redirect storage and compiled paths to /tmp if not already specified
if (!isset($_ENV['LARAVEL_STORAGE_PATH'])) {
    putenv('LARAVEL_STORAGE_PATH=/tmp/storage');
    $_ENV['LARAVEL_STORAGE_PATH'] = '/tmp/storage';
    $_SERVER['LARAVEL_STORAGE_PATH'] = '/tmp/storage';
}

if (!isset($_ENV['VIEW_COMPILED_PATH'])) {
    putenv('VIEW_COMPILED_PATH=/tmp/views');
    $_ENV['VIEW_COMPILED_PATH'] = '/tmp/views';
    $_SERVER['VIEW_COMPILED_PATH'] = '/tmp/views';
}

require __DIR__ . '/../public/index.php';

