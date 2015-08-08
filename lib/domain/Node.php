<?php namespace bbschedule\lib\domain;

class Node extends Domain {
    const TYPE_TASK_SERVER = 1;
    const TYPE_JOB_SERVER = 2;

    public $name;
    public $type;
    public $ip;
    public $port;
    public $load;
    public $ts;

    public function to_str() {
        return $this->name . '[' . $this->ip . ':' . $this->port . ']';
    }
}
