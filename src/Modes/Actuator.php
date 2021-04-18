<?php

namespace hollisho\CronJob\Modes;

use hollisho\CronJob\CronJob;
use Workerman\Worker;

class Actuator extends AbstractMode
{
    public function config()
    {
        $cronJobServer = new Worker("tcp://".CronJob::$host.":".CronJob::$port);
        $cronJobServer->protocol = CronJob::$protocolClass;

        $cronJobServer->count = CronJob::$processCount;

        $cronJobServer->onMessage = array($this, 'onMessage');
        CronJob::$workermanLog && Worker::$logFile = CronJob::$workermanLog;
        CronJob::$workermanPid && Worker::$pidFile = CronJob::$workermanPid;
    }
}
