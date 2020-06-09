<?php

namespace think\swoole;

use think\Route;
use misuoka\think\DpWorker;

class Service extends \think\Service
{

    public function boot()
    {
        $this->commands(DpWorker::class);
    }

}