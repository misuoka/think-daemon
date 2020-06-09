<?php
declare (strict_types = 1);

namespace misuoka\think\command;

use misuoka\think\PidManager;
use misuoka\think\WorkerDistribute;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class DpWorker extends Command
{
    public function configure()
    {
        $this->setName('dpworker')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload|status", 'start')
            ->addOption('d', '-d', Option::VALUE_NONE, '守护进程方式启动')
            ->setDescription('Messenger worker service for ThinkPHP');
    }

    protected function initialize(Input $input, Output $output)
    {
        $this->app->bind(PidManager::class, function () {
            return new PidManager($this->app->config->get("dpworker.service.options.pid_file"));
        });
    }

    public function handle()
    {
        $this->checkEnvironment();

        $action = $this->input->getArgument('action');

        if (in_array($action, ['start', 'stop', 'reload', 'restart', 'status'])) {
            $this->app->invokeMethod([$this, $action], [], true);
        } else {
            $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload .</error>");
        }
    }

    /**
     * 检查环境
     */
    protected function checkEnvironment()
    {
        if (!extension_loaded('swoole')) {
            $this->output->error('Can\'t detect Swoole extension installed.');

            exit(1);
        }

        if (!version_compare(swoole_version(), '4.3.1', 'ge')) {
            $this->output->error('Your Swoole version must be higher than `4.3.1`.');

            exit(1);
        }
    }

    /**
     * 启动server
     * @access protected
     * @param RpcManager $manager
     * @param PidManager $pidManager
     * @return void
     */
    protected function start(PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->output->writeln('<error>messenger service worker process is already running.</error>');
            return;
        }

        $this->output->writeln('Starting messenger service worker process ...');

        if ($this->input->hasOption('d')) {
            \Swoole\Process::daemon(true, true); // 设置为守护进程
        } else {
            $this->output->writeln('You can exit with <info>`CTRL-C`</info>');
        }

        $pid = posix_getpid();
        // printf("主进程号: {$pid}\n");
        $wd = new WorkerDistribute();
        $pidManager->create($pid, 0);
        $wd->run();
    }

    /**
     * 柔性重启server
     * @access protected
     * @param PidManager $manager
     * @return void
     */
    protected function reload(PidManager $manager)
    {
        if (!$manager->isRunning()) {
            $this->output->writeln('<error>no messenger service worker process running.</error>');
            return;
        }

        $this->output->writeln('Reloading messenger service worker process...');

        if (!$manager->killProcess(SIGUSR1)) {
            $this->output->error('> failure');
            return;
        }

        $this->output->writeln('> success');
    }

    /**
     * 停止server
     * @access protected
     * @param PidManager $manager
     * @return void
     */
    protected function stop(PidManager $manager)
    {
        if (!$manager->isRunning()) {
            $this->output->writeln('<error>no messenger service worker process running.</error>');
            return;
        }

        $this->output->writeln('Stopping messenger service worker process...');

        $isRunning = $manager->killProcess(SIGTERM, 15);

        if ($isRunning) {
            $this->output->error('Unable to stop the messenger service worker process.');
            return;
        }

        $this->output->writeln('> success');
    }

    /**
     * 重启server
     * @access protected
     * @param RpcManager $manager
     * @param PidManager $pidManager
     * @return void
     */
    protected function restart(PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->stop($pidManager);
        }

        $this->start($pidManager);
    }

    protected function status(PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->output->writeln('messenger service worker process is running.');
            if (!$pidManager->killProcess(SIGUSR2)) {
                $this->output->error('> failure');

                return;
            }
        } else {
            $this->output->writeln('<error>no messenger service worker process running.</error>');
        }
    }
}
