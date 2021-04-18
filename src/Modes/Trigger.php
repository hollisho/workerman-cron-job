<?php

namespace hollisho\CronJob\Modes;


use hollisho\CronJob\CronJob;
use Workerman\Worker;

class Trigger extends AbstractMode
{
    public function config()
    {
        $cronJobServer = new Worker();
        $cronJobServer->protocol = CronJob::$protocolClass;
        $cronJobServer->reloadable = false;
        $cronJobServer->cronList = CronJob::$cronList;

        $cronJobServer->count = 1;

        $cronJobServer->onWorkerStart = array($this, 'onWorkerStart');
        $cronJobServer->onWorkerReload = array($this, 'onWorkerReload');
        CronJob::$workermanLog && Worker::$logFile = CronJob::$workermanLog;
        CronJob::$workermanPid && Worker::$pidFile = CronJob::$workermanPid;
    }
}
