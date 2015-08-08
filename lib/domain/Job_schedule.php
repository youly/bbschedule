<?php namespace bbschedule\lib\domain;

class Job_schedule extends Domain {
    public $id;
    public $job_id;
    public $worker;
    public $gmt_start;
    public $gmt_end;
    public $is_running;
    public $pid;
    public $memory;
    public $exit_code;
    public $result;
    public $gmt_create;
}
