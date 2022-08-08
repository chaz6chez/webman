<?php

namespace app\controller;

use support\Request;

class Index
{
    public function index(Request $request)
    {
        worker_start('aaa',[
            'listen'    => 'http://0.0.0.0:8888',
            'transport' => 'tcp',
            'handler'   => process\HttpServer::class,
            'context'   => [],
            'count'     => cpu_count(),
            'user'      => '',
            'group'     => '',
            'reusePort'   => false,
            'reloadable'  => false,
            'constructor' => []
        ]);
        return response('hello webman');
    }

    public function view(Request $request)
    {
        return view('index/view', ['name' => 'webman']);
    }

    public function json(Request $request)
    {
        return json(['code' => 0, 'msg' => 'ok']);
    }

}
