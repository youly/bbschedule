<?php namespace bbschedule\provider\jobprovider;

use \bbschedule\lib\common\Mysqli;
use \bbschedule\lib\common\CrontabParser;
use \bbschedule\provider\Provider_loader;
use \bbschedule\lib\domain\Job_def;
use \bbschedule\lib\domain\Job_log;
use \bbschedule\lib\domain\Job_schedule;
use \bbschedule\lib\common\Utils;

/**
 * mysql 任务提供
 */
class Mysql_job_provider implements Job_provider {
    // 数据库增删改查句柄
    private $db;

    // 当前任务列表
    private $jobs;
    
    // 任务是否加载
    private $is_load;
    
    // 任务上次加载时间
    private $loadtime;
    
    private $log;

    public function __construct($config) {
        $this->db = new Mysqli($config);
        $this->is_load = FALSE;
        $this->loadtime = 0;
        $this->jobs = [];
        $this->log = Provider_loader::get('logprovider');
    }

    /**
     * 从数据库加载数据
     */
    private function load_job() {
        if ($this->is_load) {
            return;
        }
        $this->loadtime = time();
        $this->log->info('mysql_job_provider', 'loading job...');
        $jobs = $this->db->get_multi_row('SELECT * FROM `job_def` ORDER BY `priority` DESC');
        foreach ($jobs as $job) {
            $this->jobs[$job['id']] = $job;
        }
        $this->is_load = TRUE;
    }

    /**
     * 重新加载数据
     */
    private function reload_job() {
        $this->log->info('mysql_job_provider', 'reloading job...');
        $this->is_load = FALSE;
        $this->load_job();
    }

    /**
     * 获取可执行任务
     */
    public function get_executable_jobs() {
        $now = time();
        if ($now - $this->loadtime > 6) {
            $this->reload_job();
        } else {
            $this->load_job();
        }
        $executable_jobs = [];
        $jobs = $this->jobs;
        foreach ($jobs as $id => $job) {
            $seconds = CrontabParser::parse($job['period'], $now);
            if (empty($seconds)) {
                continue;
            }
            if (in_array(date('s', $now), $seconds)) {
                $job_def = new Job_def();
                $job_def->from_arr($job);
                $executable_jobs[$id] = $job_def;
            }
        }
        return $executable_jobs;
    }

    /**
     * 开始调度一个任务
     */
    public function schedule(Job_schedule $job_schedule) {
        $fields = $values = [];
        if ($job_schedule->job_id) {
            $fields[] = '`job_id`';
            $values[] = $job_schedule->job_id;
        }
        if (!empty($job_schedule->worker)) {
            $fields[] = '`worker`';
            $values[] = "'" . Utils::add_slashes($job_schedule->worker) . "'";
        }
        $fields[] = '`gmt_start`';
        if ($job_schedule->gmt_start) {
            $values[] = $job_schedule->gmt_start;
        } else {
            $values[] = time();
        }
        if ($job_schedule->gmt_end) {
            $fields = '`gmt_end`';
            $values[] = $job_schedule->gmt_end;
        }
        $fields[] = '`is_running`';
        if ($job_schedule->is_running) {
            $values[] = 1;
        } else {
            $values[] = 0;
        }
        if ($job_schedule->pid) {
            $fields[] = '`pid`';
            $values[] = $job_schedule->pid;
        }
        if (!empty($job_schedule->memory)) {
            $fields[] = '`memory`';
            $values[] = "'" . Utils::add_slashes($job_schedule->memory) . "'";
        }
        if (!is_null($job_schedule->exit_code)) {
            $fields[] = '`exit_code`';
            $values[] = $job_schedule->exit_code;
        }
        if (!empty($job_schedule->result)) {
            $fields[] = '`result`';
            $values[] = "'" . Utils::add_slashes($job_schedule->result) . "'";
        }
        $fields[] = '`gmt_create`';
        if ($job_schedule->gmt_create) {
            $values[] = $job_schedule->gmt_create;
        } else {
            $values = time();
        }
        $sql = "INSERT INTO `job_schedule` (%s) VALUES (%s)";
        $sql = sprintf($sql, implode(', ', $fields) , implode(', ', $values));
        $this->db->execute($sql);
        return $this->db->insert_id();
    }

    /**
     * 任务调度完成
     */
    public function feedback(Job_schedule $job_schedule) {
        $fields = [];
        $values = [];
        if (!empty($job_schedule->worker)) {
            $fields[] = "`worker` = '%s'";
            $values[] = $job_schedule->worker;
        }
        if ($job_schedule->gmt_end != 0) {
            $fields[] = '`gmt_end` = %d';
            $values[] = $job_schedule->gmt_end;
        }
        if (!is_null($job_schedule->is_running)) {
            $fields[] = '`is_running` = %d';
            $values[] = $job_schedule->is_running;
        }
        if ($job_schedule->pid) {
            $fields[] = '`pid` = %d';
            $values[] = $job_schedule->pid;
        }
        if (!empty($job_schedule->memory)) {
            $fields[] = "`memory` = '%s'";
            $values[] = $job_schedule->memory;
        }
        if (!is_null($job_schedule->exit_code)) {
            $fields[] = "`exit_code` = %d";
            $values[] = $job_schedule->exit_code;
        }
        if (!empty($job_schedule->result)) {
            $fields[] = "`result` = '%s'";
            $values[] = $job_schedule->result;
        }
        $sql = "UPDATE `job_schedule` SET ";
        $sql .= implode(', ', $fields);
        $sql .= " WHERE `id` = %d";
        $values[] = $job_schedule->id;
        $values = Utils::add_slashes($values);
        $sql = vsprintf($sql, $values);
        $this->db->execute($sql);
        return $this->db->affected_rows();
    }

    public function log(Job_log $log) {
        $sql = "INSERT INTO `job_log` (`schedule_id`, `job_id`, `pid`, `log`, `gmt_create`) VALUES (%d, %d, %d, '%s', %d)";
        $fields = [$log->schedule_id, $log->job_id, $log->pid, Utils::add_slashes($log->log), $log->gmt_create];
        $sql = vsprintf($sql, $fields);
        $this->db->execute($sql);
        return $this->db->insert_id();
    }
}
