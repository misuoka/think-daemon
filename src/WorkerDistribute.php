<?php

namespace misuoka\think;

use Swoole\Process;
use think\facade\Config;

class WorkerDistribute
{
    /**
     * 业务配置
     *
     * @var [type]
     */
    private $commands;

    /**
     * 工作进程
     *
     * @var array
     */
    private $workers = [];

    /**
     * 主进程ID
     *
     * @var [type]
     */
    private $masterPid = null;

    public function __construct()
    {
        $this->masterPid = \getmypid();
    }

    /*
     *
     * @return void
     */
    public function run()
    {
        $this->loadWorkers();

        pcntl_signal(SIGUSR1, function () {
            $this->loadWorkers();
            printf("[%s] 重载配置\n", date("Y-m-d H:i:s"));
        });

        pcntl_signal(SIGUSR2, function () {
            if (Process::kill($this->masterPid, 0)) {
                // 判断当前进程不存在，退出子进程
                printf("[%s] Service Status:\n", date("Y-m-d H:i:s"));
                printf("Workers count: %d\n", count($this->workers));
                echo "Memory Used  :\t" . sprintf("%.4f", memory_get_usage() / (1024 * 1024)) . " MB\n";
            } 
        });

        // ctrl + c, ctrl + d
        pcntl_signal(SIGINT, function () {
            printf("[%s] 退出主进程\n", date("Y-m-d H:i:s"));
            exit(0); // ? 有必要么
        });

        $this->waitAndRestart();
    }

    /**
     * 收回进程并重启
     */
    private function waitAndRestart()
    {
        while (true) {
            pcntl_signal_dispatch();
            
            if ($ret = Process::wait(false)) {
                $pid   = intval($ret["pid"] ?? 0);
                $index = $this->getIndexOfWorkerByPid($pid);
                
                if (false !== $index) {
                    
                    $commandName = $this->workers[$index]->getCommand()->getName();
                    $command     = $this->getCommand($commandName);

                    if (!$command) {
                        printf("新配置里不存在业务 %s，即将移除\n", $commandName);
                        $this->workers[$index]->setRemoving(true);
                    }

                    if ($this->workers[$index]->isRemoving()) {
                        unset($this->workers[$index]);
                        printf("[%s] 移除守护 %s\n", date("Y-m-d H:i:s"), $commandName);
                    } else {
                        $this->runWorker($this->workers[$index]);
                        printf("[%s] 重新拉起 %s\n", date("Y-m-d H:i:s"), $commandName);
                    }
                }
            }
            usleep(300000); // 0.3s
        }
    }

    private function loadWorkers()
    {
        $this->parseConfig();

        foreach ($this->commands as $command) {
            if ($command->isEnabled()) {
                $this->startWorker($command);
            } else {
                $this->removeWorker($command);
            }
        }
    }

    private function parseConfig()
    {
        Config::load('dpworker', 'service');
        $config         = Config::get('dpworker.service.workers');
        $this->commands = [];

        foreach ($config as $name => $item) {
            $command          = new Command($name, $item);
            $this->commands[] = $command;
        }
    }

    private function startWorker(Command $command)
    {
        $index = $this->getIndexOfWorker($command->getName());
        if (false === $index) {
            $worker = new Worker($command);
            $this->runWorker($worker);
            $this->workers[] = $worker;
        } else {
            // 先停止后重新创建
            $this->workers[$index]->setCommand($command); // 设置新的 command
            $this->workers[$index]->stop(); // 立即停止运行中的进程
        }
    }

    private function removeWorker(Command $command)
    {
        $index = $this->getIndexOfWorker($command->getName());
        if (false !== $index) {
            $this->workers[$index]->stop();
            $this->workers[$index]->setRemoving(true);
        }
    }

    private function getIndexOfWorker(string $commandName)
    {
        foreach ($this->workers as $index => $worker) {
            if ($commandName == $worker->getCommand()->getName()) {
                return $index;
            }
        }
        return false;
    }

    private function getIndexOfWorkerByPid($pid)
    {
        foreach ($this->workers as $index => $worker) {
            if ($pid == $worker->getPid()) {
                return $index;
            }
        }
        return false;
    }

    private function getCommand($commandName)
    {
        foreach ($this->commands as $command) {
            if ($commandName == $command->getName()) {
                return $command;
            }
        }
        return false;
    }

    private function runWorker(Worker $worker)
    {
        $masterPid = $this->masterPid;
        $process   = new Process(function (Process $proc) use ($worker, $masterPid) {
            $worker->run(function () use ($proc, $masterPid) {
                if (!Process::kill($masterPid, 0)) {
                    printf("[%s] 父进程不存在，退出子进程\n", date("Y-m-d H:i:s"));
                    $proc->exit();
                }
            });
        }, true, 1);

        $pid = $process->start();
        $worker->setPid($pid);

        return $pid;
    }
}
