<?php namespace bbschedule\jobtracker;

use \bbschedule\provider\Provider_loader;
use \bbschedule\lib\config\Config_holder;
use \bbschedule\jobtracker\Job_tracker;

class Job_tracker_runner {
    
    private static $options = 'h:p:';
    private static $longoptions = array();
    
    public static function run() {
        $options = getopt(self::$options, self::$longoptions);
        $config_holder = Config_holder::get_instance();
        $config_server = $config_holder->get_section('common');
        $config_server = array_merge($config_server, $config_holder->get_section('jobtracker'));
        if (!empty($options['h'])) {
            $config_server['ip'] = $options['h'];
        }
        if (!empty($options['p'])) {
            $config_server['port'] = $options['p'];
        }
        $job_tracker = new Job_tracker($config_server);
        $job_tracker->start();
    }
}
