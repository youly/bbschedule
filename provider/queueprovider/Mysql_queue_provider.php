<?php namespace bbschedule\provider\queueprovider;

use \bbschedule\lib\domain\Job_msg;
use \bbschedule\lib\common\Mysqli;
use \bbschedule\lib\common\Utils;

/**
 * mysql 队列
 */
class Mysql_queue_provider implements Queue_provider {
    
    private $db;

    public function __construct($config) {
        $this->db = new Mysqli($config);      
    }

    public function consume($auto_ack = FALSE) {
        $sql = 'SELECT * FROM `job_msg_queue` ORDER BY priority DESC, id ASC LIMIT 1';
        $row = $this->db->get_one_row($sql);
        if (empty($row)) {
            return NULL;
        }
        $job_msg = new Job_msg();
        $job_msg->from_arr($row);
        // 不是自动ack， 放入pending队列
        if (!$auto_ack) {
            $insert_id = $this->pending($job_msg);
            if ($insert_id) {
                $this->delete($job_msg);
            }
        }
        return $job_msg;
    }

    public function publish(Job_msg $msg) {
        $sql = "INSERT INTO `job_msg_queue` (`body`, `priority`, `gmt_create`) VALUES ('%s', %d, %d)";
        $fields = [$msg->body, $msg->priority, $msg->gmt_create];
        $fields = Utils::add_slashes($fields);
        $sql = vsprintf($sql, $fields);
        echo $sql, "\n";
        $this->db->execute($sql);
        return $this->db->insert_id();
    }

    private function delete(Job_msg $msg) {
        $sql = "DELETE FROM `job_msg_queue` WHERE id = %s";
        $sql = sprintf($sql, $msg->id);
        $this->db->execute($sql);
        return $this->db->affected_rows();
    }

    public function ack(Job_msg $msg) {
        $sql = "DELETE FROM `job_msg_queue_pending` WHERE id = %s";
        $sql = sprintf($sql, $msg->id);
        $this->db->execute($sql);
        return $this->db->affected_rows();
    }

    private function pending(Job_msg $msg) {
        $sql = "INSERT INTO `job_msg_queue_pending` SELECT * FROM `job_msg_queue` where id = %s";
        $sql = sprintf($sql, $msg->id);
        $this->db->execute($sql);
        return $this->db->insert_id();
    }
}
