#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Webman\Config;

ini_set('display_errors', 'on');
error_reporting(E_ALL);

if (class_exists('Dotenv\Dotenv') && file_exists(base_path() . '/.env')) {
    if (method_exists('Dotenv\Dotenv', 'createUnsafeImmutable')) {
        Dotenv\Dotenv::createUnsafeImmutable(base_path())->load();
    } else {
        Dotenv\Dotenv::createMutable(base_path())->load();
    }
}

Config::load(config_path(), ['route', 'container']);

error_reporting(config('app.error_reporting', E_ALL));

if ($timezone = config('app.default_timezone')) {
    date_default_timezone_set($timezone);
}

if ( !file_exists($runtime_logs_path = runtime_path() . DIRECTORY_SEPARATOR . 'logs') || !is_dir($runtime_logs_path) ) {
    if (!mkdir($runtime_logs_path,0777,true)) {
        throw new \RuntimeException('Failed to create runtime logs directory. Please check the permission.');
    }
}

if ( !file_exists($runtime_views_path = runtime_path() . DIRECTORY_SEPARATOR . 'views') || !is_dir($runtime_views_path) ) {
    if (!mkdir($runtime_views_path,0777,true)) {
        throw new \RuntimeException('Failed to create runtime views directory. Please check the permission.');
    }
}

master_init(config('app', []));

foreach (config('process', []) as $process_name => $config) {
    // Windows does not support custom processes.
    if(\DIRECTORY_SEPARATOR !== '/') {
        if($process_name === 'webman') {
            worker_start($process_name, $config);
        }
        continue;
    }
    worker_start($process_name, $config);
    foreach (config('plugin', []) as $firm => $projects) {
        foreach ($projects as $name => $project) {
            foreach ($project['process'] ?? [] as $process_name => $config) {
                worker_start("plugin.$firm.$name.$process_name", $config);
            }
        }
    }
}

Worker::runAll();
