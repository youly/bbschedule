<?php namespace bbschedule\lib\domain;

class Job_def extends Domain {
    public $id;
    public $name;
    public $type;
    public $priority;
    public $command;
    public $env;
    public $concurrent;
    public $maxtime;
    public $period;
    public $group;
    public $gmt_create;
    public $gmt_modified;
}

