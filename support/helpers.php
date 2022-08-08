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
use support\protocols\SslListenerInterface;
use support\protocols\TcpListenerInterface;
use support\protocols\UdpListenerInterface;
use support\Request;
use support\Response;
use support\Translation;
use support\view\Blade;
use support\view\Raw;
use support\view\ThinkPHP;
use support\view\Twig;
use Webman\App;
use Webman\Config;
use Webman\Route;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

// Phar support.
if (is_phar()) {
    define('BASE_PATH', dirname(__DIR__));
} else {
    define('BASE_PATH', realpath(__DIR__ . '/../'));
}
const WEBMAN_VERSION = '1.3.0';

/**
 * @param bool $return_phar
 * @return false|string
 */
function base_path(bool $return_phar = true)
{
    static $real_path = '';
    if (!$real_path) {
        $real_path = is_phar() ? dirname(Phar::running(false)) : BASE_PATH;
    }
    return $return_phar ? BASE_PATH : $real_path;
}

/**
 * @return string
 */
function app_path(): string
{
    return BASE_PATH . DIRECTORY_SEPARATOR . 'app';
}

/**
 * @return string
 */
function public_path(): string
{
    static $path = '';
    if (!$path) {
        $path = config('app.public_path', BASE_PATH . DIRECTORY_SEPARATOR . 'public');
    }
    return $path;
}

/**
 * @return string
 */
function config_path(): string
{
    return BASE_PATH . DIRECTORY_SEPARATOR . 'config';
}

/**
 * Phar support.
 * Compatible with the 'realpath' function in the phar file.
 *
 * @return string
 */
function runtime_path(): string
{
    static $path = '';
    if (!$path) {
        $path = config('app.runtime_path', BASE_PATH . DIRECTORY_SEPARATOR . 'runtime');
    }
    return $path;
}

/**
 * @param string $body
 * @param int $status
 * @param array $headers
 * @return Response
 */
function response(string $body = '', int $status = 200, array $headers = []): Response
{
    return new Response($status, $headers, $body);
}

/**
 * @param $data
 * @param int $options
 * @return Response
 */
function json($data, int $options = JSON_UNESCAPED_UNICODE): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode($data, $options));
}

/**
 * @param $xml
 * @return Response
 */
function xml($xml): Response
{
    if ($xml instanceof SimpleXMLElement) {
        $xml = $xml->asXML();
    }
    return new Response(200, ['Content-Type' => 'text/xml'], $xml);
}

/**
 * @param $data
 * @param string $callback_name
 * @return Response
 */
function jsonp($data, string $callback_name = 'callback'): Response
{
    if (!is_scalar($data) && null !== $data) {
        $data = json_encode($data);
    }
    return new Response(200, [], "$callback_name($data)");
}

/**
 * @param $location
 * @param int $status
 * @param array $headers
 * @return Response
 */
function redirect($location, int $status = 302, array $headers = []): Response
{
    $response = new Response($status, ['Location' => $location]);
    if (!empty($headers)) {
        $response->withHeaders($headers);
    }
    return $response;
}

/**
 * @param $template
 * @param array $vars
 * @param null $app
 * @return Response
 */
function view($template, array $vars = [], $app = null): Response
{
    static $handler;
    if (null === $handler) {
        $handler = config('view.handler');
    }
    return new Response(200, [], $handler::render($template, $vars, $app));
}

/**
 * @param $template
 * @param array $vars
 * @param $app
 * @return Response
 * @throws Throwable
 */
function raw_view($template, array $vars = [], $app = null): Response
{
    return new Response(200, [], Raw::render($template, $vars, $app));
}

/**
 * @param $template
 * @param array $vars
 * @param null $app
 * @return Response
 */
function blade_view($template, array $vars = [], $app = null): Response
{
    return new Response(200, [], Blade::render($template, $vars, $app));
}

/**
 * @param $template
 * @param array $vars
 * @param null $app
 * @return Response
 */
function think_view($template, array $vars = [], $app = null): Response
{
    return new Response(200, [], ThinkPHP::render($template, $vars, $app));
}

/**
 * @param $template
 * @param array $vars
 * @param null $app
 * @return Response
 */
function twig_view($template, array $vars = [], $app = null): Response
{
    return new Response(200, [], Twig::render($template, $vars, $app));
}

/**
 * @return Request
 */
function request(): Request
{
    return App::request();
}

/**
 * @param $key
 * @param null $default
 * @return mixed
 */
function config($key = null, $default = null)
{
    return Config::get($key, $default);
}

/**
 * @param $name
 * @param ...$parameters
 * @return string
 */
function route($name, ...$parameters): string
{
    $route = Route::getByName($name);
    if (!$route) {
        return '';
    }

    if (!$parameters) {
        return $route->url();
    }

    if (is_array(current($parameters))) {
        $parameters = current($parameters);
    }

    return $route->url($parameters);
}

/**
 * @param mixed $key
 * @param mixed $default
 * @return mixed
 */
function session($key = null, $default = null)
{
    $session = request()->session();
    if (null === $key) {
        return $session;
    }
    if (\is_array($key)) {
        $session->put($key);
        return null;
    }
    if (\strpos($key, '.')) {
        $key_array = \explode('.', $key);
        $value = $session->all();
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }
    return $session->get($key, $default);
}

/**
 * @param null|string $id
 * @param array $parameters
 * @param string|null $domain
 * @param string|null $locale
 * @return string
 */
function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): ?string
{
    $res = Translation::trans($id, $parameters, $domain, $locale);
    return $res === '' ? $id : $res;
}

/**
 * @param string|null $locale
 * @return string|void
 */
function locale(string $locale = null)
{
    if (!$locale) {
        return Translation::getLocale();
    }
    Translation::setLocale($locale);
}

/**
 * 404 not found
 *
 * @return Response
 */
function not_found(): Response
{
    return new Response(404, [], file_get_contents(public_path() . '/404.html'));
}

/**
 * Copy dir.
 * @param $source
 * @param $dest
 * @param bool $overwrite
 * @return void
 */
function copy_dir($source, $dest, bool $overwrite = false)
{
    if (is_dir($source)) {
        if (!is_dir($dest)) {
            mkdir($dest);
        }
        $files = scandir($source);
        foreach ($files as $file) {
            if ($file !== "." && $file !== "..") {
                copy_dir("$source/$file", "$dest/$file");
            }
        }
    } else if (file_exists($source) && ($overwrite || !file_exists($dest))) {
        copy($source, $dest);
    }
}

/**
 * Remove dir.
 * @param $dir
 * @return bool
 */
function remove_dir($dir): bool
{
    if (is_link($dir) || is_file($dir)) {
        return unlink($dir);
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file") && !is_link($dir)) ? remove_dir("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

/**
 * @param $worker
 * @param $class
 */
function worker_bind($worker, $class)
{
    $callback_map = [
        'onConnect',
        'onMessage',
        'onClose',
        'onError',
        'onBufferFull',
        'onBufferDrain',
        'onWorkerStop',
        'onWebSocketConnect'
    ];
    foreach ($callback_map as $name) {
        if (method_exists($class, $name)) {
            $worker->$name = [$class, $name];
        }
    }
    if (method_exists($class, 'onWorkerStart')) {
        call_user_func([$class, 'onWorkerStart'], $worker);
    }
}

/**
 * @param $process_name
 * @param $config
 * @return void
 */
function worker_start($process_name, $config)
{
    if (!isset($config['handler']) or !class_exists($config['handler'])) {
        echo "process error: class {$config['handler']} not exists\r\n";
        return;
    }
    $worker = new Worker($config['listen'] ?? null, $config['context'] ?? []);
    $property_map = [
        'count',
        'user',
        'group',
        'reloadable',
        'reusePort',
        'transport',
        'protocol',
    ];
    $worker->name = $process_name;
    foreach ($property_map as $property) {
        if (isset($config[$property])) {
            $worker->$property = $config[$property];
        }
    }
    $instance = Container::make($config['handler'], $config['constructor'] ?? []);
    if($instance instanceof TcpListenerInterface){
        $worker->transport = 'tcp';
    }
    if($instance instanceof UdpListenerInterface){
        $worker->transport = 'udp';
    }
    if($instance instanceof SslListenerInterface){
        $worker->transport = 'ssl';
    }

    $worker->onWorkerStart = function ($worker) use ($process_name, $config, $instance) {
        require_once base_path() . '/support/bootstrap.php';
        worker_bind($worker, $instance);
    };
    unset($worker);
}

/**
 * @param array $config
 * @return void
 */
function master_init(array $config){
    Worker::$pidFile = $config['pid_file'] ?? runtime_path() . '/webman.pid';
    Worker::$stdoutFile = $config['stdout_file'] ?? runtime_path() . '/logs/stdout.log';
    Worker::$logFile = $config['log_file'] ?? runtime_path() . '/logs/workerman.log';
    Worker::$eventLoopClass = $config['event_loop'] ?? '';
    TcpConnection::$defaultMaxPackageSize = $config['max_package_size'] ?? 10 * 1024 * 1024;
    if (property_exists(Worker::class, 'statusFile')) {
        Worker::$statusFile = $config['status_file'] ?? '';
    }
    if (property_exists(Worker::class, 'stopTimeout')) {
        Worker::$stopTimeout = $config['stop_timeout'] ?? 2;
    }

    Worker::$onMasterReload = function () {
        if (function_exists('opcache_get_status') and function_exists('opcache_invalidate')) {
            if ($status = opcache_get_status()) {
                if (isset($status['scripts']) && $scripts = $status['scripts']) {
                    foreach (array_keys($scripts) as $file) {
                        opcache_invalidate($file, true);
                    }
                }
            }
        }
    };
}


/**
 * Phar support.
 * Compatible with the 'realpath' function in the phar file.
 *
 * @param string $file_path
 * @return string
 */
function get_realpath(string $file_path): string
{
    if (strpos($file_path, 'phar://') === 0) {
        return $file_path;
    } else {
        return realpath($file_path);
    }
}

/**
 * @return bool
 */
function is_phar(): bool
{
    return class_exists(\Phar::class, false) && Phar::running();
}

/**
 * @return int
 */
function cpu_count(): int
{
    // Windows does not support the number of processes setting.
    if (\DIRECTORY_SEPARATOR === '\\') {
        return 1;
    }
    $count = 4;
    if (is_callable('shell_exec')) {
        if (strtolower(PHP_OS) === 'darwin') {
            $count = (int)shell_exec('sysctl -n machdep.cpu.core_count');
        } else {
            $count = (int)shell_exec('nproc');
        }
    }
    return $count > 0 ? $count : 4;
}
