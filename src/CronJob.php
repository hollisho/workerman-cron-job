<?php
/**
 * Created by PhpStorm.
 * User: hollis
 * Date: 2019/10/15
 * Time: 下午8:56
 */

namespace hollisho\CronJob;

use Workerman\Worker;

class CronJob {

    /**
     * @var string
     */
    protected static $configDir = "";

    protected static $configMap = [];

    protected static $mode = "both";

    protected static $modeList = ['trigger', 'actuator', 'both'];

    public static $host = "127.0.0.1";
    public static $port = "8888";
    public static $processCount = 4;
    public static $protocolClass = "Workerman\\Protocols\\Text";

    public static $env = '';
    public static $errorLog = '';
    public static $outLog = '';
    public static $workermanLog = '';
    public static $workermanPid = '';

    public static $cronList = [];

    public static function run ($configDir = "")
    {
        try {
            self::setConfigDir($configDir);
            self::init();
        } catch (\Exception $e) {
            print_r($e->getMessage());
            return;
        }

        $mode = self::modeFactory();
        $mode->config();

        // 运行worker
        Worker::runAll();
    }

    protected static function setConfigDir($configDir = "")
    {
        self::$configDir = __DIR__.'/default-config.json';

        if (!empty($configDir) && file_exists($configDir)) {
            self::$configDir = $configDir;
        }
    }

    /**
     * @throws CronJobException
     */
    protected static function init()
    {
        self::checkConfig();
        self::resolveConfig();
    }

    /**
     * @throws CronJobException
     */
    protected static function checkConfig()
    {
        $config = self::readConfigFile(self::$configDir);
        if (!is_array($config)) {
            throw new CronJobException(self::t("配置文件返回值必须为数组"));
        }
        if (!isset($config['mode'])) {
            throw new CronJobException(self::t("必须指定模式(mode)"));
        }
        $mode = $config['mode'];
        if (!in_array($mode, self::$modeList)) {
            throw new CronJobException(self::t("模式(mode)配置错误"));
        }

        if (!isset($config['port'])) {
            throw new CronJobException(self::t("端口(port)未配置"));
        }

        switch ($mode) {
            case 'trigger':
                if (!isset($config['host'])) {
                    throw new CronJobException(self::t('执行器host未配置'));
                }
                break;
            case 'actuator':
                if (!isset($config['processCount'])) {
                    throw new CronJobException(self::t('processCount未配置'));
                }
                break;
            case 'both':
                if (!isset($config['processCount'])) {
                    throw new CronJobException(self::t('processCount未配置'));
                }
                break;
        }
        if (!isset($config['cron'])) {
            throw new CronJobException(self::t("定时任务(cron) 未配置"));
        }

        self::$configMap = $config;
    }

    protected static function resolveConfig()
    {
        $config = self::$configMap;
        self::$mode = $config['mode'];
        self::$host = $config['host'] ? $config['host'] : '127.0.0.1';
        self::$port = $config['port'];
        self::$processCount = $config['processCount'] ? $config['processCount'] : 4;
        self::$env = $config['execution-env'] ? $config['execution-env'] : '';
        self::$outLog = $config['stdout-log-file'] ? $config['stdout-log-file'] : '';
        self::$errorLog = $config['stderr-log-file'] ? $config['stderr-log-file'] : '';
        self::$workermanLog = $config['workerman-log-file'] ? $config['workerman-log-file'] : '';
        self::$workermanPid = $config['workerman-pid-file'] ? $config['workerman-pid-file'] : '';
        self::$cronList = self::parseCron(self::$configMap['cron']);
    }

    public static function reloadCron()
    {
        $config = self::readConfigFile(self::$configDir);
        return self::parseCron($config['cron']);
    }

    public static function readConfigFile($path)
    {
        return json_decode(file_get_contents($path), true);
    }

    public static function parseCron($cronConfig)
    {
        $dimensions = array(
            array(0,59), //Seconds
            array(0,59), //Minutes
            array(0,23), //Hours
            array(1,31), //Days
            array(1,12), //Months
            array(0,6),  //Weekdays
        );

        $tmp = [];
        foreach ($cronConfig as $task => $config) {
            foreach ($config as $key => $item) {
                list($piece, $step) = explode('/', $item, 2) + array(false, 1);

                if ($piece === '*') {
                    $list = range($dimensions[$key][0], $dimensions[$key][1]);
                    if ($step > 1) {
                        foreach ($list as $k => &$v) {
                            if ($v % $step !== 0) {
                                unset($list[$k]);
                            }
                        }
                    }
                } else {
                    $list = [];
                    foreach (explode(',', $item) as $value) {
                        $range = explode('-', $value);
                        if (count($range) === 2) {
                            $list = array_merge($list, range($range[0], $range[1]));
                        } else {
                            array_push($list, intval($range[0]));
                        }
                    }
                }
                $tmp[$task][$key] = $list;
            }
        }

        return $tmp;
    }

    public static function t($msg)
    {
        return $msg;
    }

    /**
     * @return AbstractMode
     */
    protected static function modeFactory()
    {
        $modeClass = 'hollisho\CronJob\Modes\\'.ucfirst(self::$mode);
        $mode = new $modeClass();
        return $mode;
    }
}
