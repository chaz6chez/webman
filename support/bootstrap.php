<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Container;
use Webman\Bootstrap;
use Webman\Config;
use Webman\Route;
use Webman\Middleware;

$worker = $worker ?? null;

if ($timezone = config('app.default_timezone')) {
    date_default_timezone_set($timezone);
}

set_error_handler(function ($level, $message, $file = '', $line = 0, $context = []) {
    if (error_reporting() & $level) {
        throw new ErrorException($message, 0, $level, $file, $line);
    }
});

if ($worker) {
    register_shutdown_function(function ($start_time) {
        if (time() - $start_time <= 1) {
            sleep(1);
        }
    }, time());
}

if (class_exists('Dotenv\Dotenv') && file_exists(base_path() . '/.env')) {
    if (method_exists('Dotenv\Dotenv', 'createUnsafeImmutable')) {
        Dotenv\Dotenv::createUnsafeImmutable(base_path())->load();
    } else {
        Dotenv\Dotenv::createMutable(base_path())->load();
    }
}

Config::reload(config_path(), ['route', 'container']);

foreach (config('plugin', []) as $firm => $projects) {
    foreach ($projects as $name => $project) {
        foreach ($project['autoload']['files'] ?? [] as $file) {
            include_once $file;
        }
    }
}

foreach (config('autoload.files', []) as $file) {
    include_once $file;
}

$container = Container::instance();
Route::container($container);
Middleware::container($container);

Middleware::load(config('middleware', []));
foreach (config('plugin', []) as $firm => $projects) {
    foreach ($projects as $name => $project) {
        Middleware::load($project['middleware'] ?? []);
    }
}
Middleware::load(['__static__' => config('static.middleware', [])]);

/** @var Bootstrap $class_name */
foreach (config('bootstrap', []) as $class_name) {
    if($worker){
        $class_name::start($worker);
    }
}

foreach (config('plugin', []) as $firm => $projects) {
    foreach ($projects as $name => $project) {
        /** @var Bootstrap $class_name */
        foreach ($project['bootstrap'] ?? [] as $class_name) {
            if($worker){
                $class_name::start($worker);
            }
        }
    }
}

Route::load(config_path());

