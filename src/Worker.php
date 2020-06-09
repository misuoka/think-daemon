<?php

namespace misuoka\think;

class Worker
{
    private $command;

    private $pid;

    private $removing;

    private $logic;
    
    private $timestart;

    private $controlFile;

    public function __construct(Command $command)
    {
        $this->setCommand($command);
    }

    public function setCommand(Command $command) 
    {
        $this->command = $command;
        $this->logic = $command->getLogic();
        $this->controlFile = sys_get_temp_dir() . "/" . $command->getName() . '_proccess.end';
    }

    public function setPid($pid) 
    {
        $this->pid = $pid;
    }

    public function setRemoving($removing)
    {
        $this->removing = $removing;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function isRemoving()
    {
        return $this->removing;
    }

    public function run($callback = null)
    {
        $logic = new $this->logic;
        $this->timestart = time();

        do {

            try {
                $logic->run();

                is_callable($callback) ? $callback() : null;

                \usleep($this->command->getSleeptime());
            } catch (\Exception $e) {
                // throw $th;
                // 记录异常日志 ...
                $this->stop();
            }

        } while($this->loop());
    }

    public function stop()
    {
        \file_put_contents($this->controlFile, '');
    }

    private function loop()
    {
        // 超时设置，避免进程挂死
        if(time() - $this->timestart > 60 * $this->command->getLooptime() || $this->checkEnd()) {
            return false;
        }

        return true;
    }

    private function checkEnd()
    {
        if(file_exists($this->controlFile)) {
            
            @\unlink($this->controlFile);
            return true;
        }
        return false;
    }
}