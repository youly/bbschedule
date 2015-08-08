<?php

date_default_timezone_set('Asia/Shanghai');

define('DEBUG', true);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', realpath(dirname(__FILE__)) . DS);

require './vendor/autoload.php';

class BBSchedule {

    private static $options = 't:';
    private static $longoptions = array('type:');

    public static function run() {
        $opt = getopt(self::$options, self::$longoptions);
        if (empty($opt['t']) || !in_array($opt['t'], array('jobtracker', 'tasktracker', 'httpserver'))) {
            $opt['t'] = 'jobtracker';
        }
        if ($opt['t'] == 'jobtracker') {
            \bbschedule\jobtracker\Job_tracker_runner::run();
        }
        if ($opt['t'] == 'tasktracker') {
            \bbschedule\tasktracker\Task_tracker_runner::run();
        }
        if ($opt['t'] == 'httpserver') {
            \bbschedule\jobtracker\HttpServer::run();
        }
    }
}

BBSchedule::run();
