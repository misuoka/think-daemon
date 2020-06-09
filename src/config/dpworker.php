<?php

return [
    'service' => [
        'options' => [
            // 'pid_file'
        ],
        'workers' => [
            // 'workname' => [
            //     'enabled' => true,  // 开启
            //     'logic'   => \app\logic\ReadSms::class, // 具体业务类，执行函数必须是 run 
            //     'looptime'=> 10, // 分，进程循环执行的时间。时间到了之后，会再次启动进程
            //     'sleeptime'=> 0.3, // 秒，支持小数
            // ],
            // ...
        ],
        'process' => [
            'enable_coroutine'      => false,  // 启用协程，开启后可以直接在子进程的函数中使用协程 API
        ],
    ],
];