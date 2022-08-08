<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<250220719@qq.com>
 * @copyright chaz6chez<250220719@qq.com>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace process;

use support\Container;
use support\Log;
use support\protocols\ProcessInterface;
use support\protocols\TcpListenerInterface;
use support\Request;
use Webman\App;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Worker;

/**
 * @author chaz6chez<250220719@qq.com>
 */
class HttpServer implements ProcessInterface, TcpListenerInterface
{

    /** @var App|null */
    protected $app;

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker)
    {
        require_once base_path() . '/support/bootstrap.php';
        $this->app = new App($worker, Container::instance(), Log::channel('default'), app_path(), public_path());
        Http::requestClass(config('app.request_class', config('server.request_class', Request::class)));
    }

    /**
     * @param TcpConnection $connection
     * @param $data
     * @return void
     */
    public function onMessage(TcpConnection $connection, $data){
        call_user_func([$this->app, 'onMessage'], $connection, $data);
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker){}

    /** @inheritDoc */
    public function onWorkerReload(Worker $worker){}

    /** @inheritDoc */
    public function onWorkerExit(Worker $worker, $status, $pid){}
}