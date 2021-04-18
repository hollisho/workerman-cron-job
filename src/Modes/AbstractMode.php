<?php

namespace hollisho\CronJob\Modes;


use hollisho\CronJob\CronJob;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;

abstract class AbstractMode
{
    abstract function config();

    public function onWorkerStart($cronJobServer)
    {
        if ($cronJobServer->id === 0) {
            $trigger = new AsyncTcpConnection("tcp://".CronJob::$host.":".CronJob::$port);
            $trigger->protocol = CronJob::$protocolClass;
            $trigger->connect();
            $timeInterval = 1;
            Timer::add($timeInterval, function ($cronJobServer, $trigger){
                $nowTime = explode(' ', date('s i G j n w', time()));
                foreach ($cronJobServer->cronList as $taskName => $timePieces) {
                    $sendFlag = true;
                    foreach ($timePieces as $key => $item) {
                        if (!in_array(intval($nowTime[$key]), $item)) {
                            $sendFlag = false;
                            break;
                        }
                    }
                    if ($sendFlag) {
                        $trigger->send($taskName);
                    }
                }
            }, array($cronJobServer, $trigger));
        }
    }

    public function onMessage($connection, $data)
    {
        if ((CronJob::$processCount === 1) || ($connection->worker->id !== 0)) {
            $outLog = CronJob::$outLog ? CronJob::$outLog : '/dev/null';
            $errorLog = CronJob::$errorLog ? CronJob::$errorLog : '&1';
            $command = CronJob::$env.' '.$data.' >> '.$outLog.' 2>>'.$errorLog;
            system($command.' &');
        }
    }

    public function onWorkerReload($worker)
    {
        $cronList = CronJob::reloadCron();
//        var_dump(array_keys($cronList));
        $worker->cronList = $cronList;
    }
}
