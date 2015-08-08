<?php namespace bbschedule\lib\domain;

class Job_log extends Domain {
    public $id;
    public $schedule_id;
    public $job_id;
    public $pid;
    public $log;
    public $gmt_create;
}
