<?php

namespace misuoka\think;

class Service extends \think\Service
{

    public function register()
    {
        $this->commands([
            'dpworker'       => '\\misuoka\\think\\command\\DpWorker',
        ]);
    }

}