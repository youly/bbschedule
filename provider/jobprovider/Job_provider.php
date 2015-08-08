<?php namespace bbschedule\provider\jobprovider;

use \bbschedule\lib\domain\Job_schedule;
use \bbschedule\lib\domain\Job_log;

/**
 * 任务提供接口
 */
interface Job_provider {

    public function get_executable_jobs();

    public function schedule(Job_schedule $job);

    public function feedback(Job_schedule $job);

    public function log(Job_log $log);
}
