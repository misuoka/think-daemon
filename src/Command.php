<?php

namespace misuoka\think;

class Worker
{
    private $name;

    private $logic;

    private $enabled;

    private $looptime;

    private $sleeptime;

    public function __construct($name, $config = [])
    {
        $this->name = $name;
        $this->logic = $config['logic'] ?? null;
        $this->enabled = $config['enabled']?? false;
        $this->looptime = $config['looptime']?? 10; // 10 分钟
        $this->sleeptime = !isset($config['sleeptime']) || $config['sleeptime'] <= 0 ? 300000 : $config['sleeptime']; // 0.3 秒
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLogic()
    {
        return $this->logic;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getLooptime()
    {
        return $this->looptime;
    }

    public function getSleeptime()
    {
        return $this->sleeptime;
    }
}