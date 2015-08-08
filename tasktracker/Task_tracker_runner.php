<?php namespace bbschedule\tasktracker;

use \bbschedule\provider\Provider_loader;
use \bbschedule\lib\config\Config_holder;
use \bbschedule\jobtracker\Tash_tracker;

class Task_tracker_runner {
    
    private static $options = 'h:p:';
    private static $longoptions = array('job_server_host:', 'job_server_port');
    
    public function run() {
        $options = getopt(self::$options, self::$longoptions);
        $config_holder = Config_holder::get_instance();
        $config_server = $config_holder->get_section('common');
        $config_server = array_merge($config_server, $config_holder->get_section('tasktracker'));
        if (!empty($options['h'])) {
            $config_server['ip'] = $options['h'];
        }
        if (!empty($options['p'])) {
            $config_server['port'] = $options['p'];
        }
        $config_job_server = $config_holder->get_section('jobtracker');
        if (isset($config_job_server['ip'])) {
            $config_server['job_server_ip'] = $config_job_server['ip'];
        }
        if (isset($config_job_server['port'])) {
            $config_server['job_server_port'] = $config_job_server['port'];
        }
        if (!empty($options['job_server_host'])) {
            $config_server['job_server_ip'] = $options['job_server_host'];
        }
        if (!empty($options['job_server_port'])) {
            $config_server['job_server_port'] = $options['job_server_port'];
        }
        $task_tracker = new Task_tracker($config_server);
        $task_tracker->start();
    }
}
