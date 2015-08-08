<?php namespace bbschedule\provider\queueprovider;

use \bbschedule\lib\domain\Job_msg;

interface Queue_provider {

    public function consume($auto_ack = FALSE);

    public function ack(Job_msg $msg);
}
