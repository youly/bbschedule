<?php namespace bbschedule\provider\logprovider;

interface Log_provider {
    public function error($action, $msg);
    public function warn($action, $msg);
    public function info($action, $msg);
    public function debug($action, $msg);
    public function trace($action, $msg);
}
