<?php namespace bbschedule\jobtracker;

use \bbschedule\lib\protocol\MessagePacket;
use \bbschedule\provider\Provider_loader;
use \bbschedule\lib\domain\Job_msg;
use \bbschedule\lib\domain\Job_def;
use \bbschedule\lib\domain\Job_log;
use \bbschedule\lib\domain\Job_schedule;
use \bbschedule\lib\domain\Node;
use \bbschedule\lib\protocol\Message;
use \bbschedule\lib\protocol\MsgType;

class Job_tracker {

    protected $server;
    protected $fd_buffer_map = [];

    protected $ip;
    protected $port;

    protected $workers;
    protected $scheduling_jobs;
    
    protected $log;
    protected $job_provider;
    protected $queue_provider;

    public function __construct($config) {
        $config_check_arr = array('ip', 'port');
        foreach ($config_check_arr as $key) {
            if (empty($config[$key])) {
                die("job tracker config $key not exists\n");
            }
            $this->$key = $config[$key];
        }

        $this->workers = [];
        $this->scheduling_jobs = [];

        $this->log = Provider_loader::get('logprovider');
        $this->job_provider = Provider_loader::get('jobprovider');
        $this->queue_provider = Provider_loader::get('queueprovider');

        $this->init_swoole_server();
    }

    protected function init_swoole_server() {
        $server = new \swoole_server($this->ip, $this->port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $server->on('Start', array($this,'on_start'));
        $server->on('WorkerStart', array($this,'on_worker_start'));
        $server->on('Receive', array($this,'on_receive'));
        $server->on('Close', array($this,'on_close'));
        $server->on('ManagerStart', array($this,'on_manager_start'));
        $this->server = &$server;
    }

    protected function start_schedule_process() {
        $schedule_process = new \swoole_process(array($this, 'schedule_job'));
        $schedule_process->start();
        swoole_event_add($schedule_process->pipe, function($pipe) use($schedule_process) {
            $data = $schedule_process->read();
            MessagePacket::decode($data, $msg);
            $this->dispatch_message($msg);
        });
    }

    public function start_http_process() {
        $this->log->info('job_tracker', 'http process(' . posix_getpid() . ') start...');
        $p = new \swoole_process(function ($process) {
            //$s = new \swoole_http_server('0.0.0.0', 9999);
            //$s->start();
            $process->exec($_SERVER['_'], array(ROOT_PATH . 'bbschedule.php', '-t', 'httpserver'));
        });
        $p->start();
    }

    /**
     * 任务调度子进程
     */
    public function schedule_job($process) {
        $this->log->info('job_tracker', "scheduling process(" . posix_getpid(), ") start...");
        swoole_set_process_name('JobServer-schedule');
        while (TRUE) {
            $jobs = $this->job_provider->get_executable_jobs();
            foreach ($jobs as $id => $job) {
                $msg = new Message();
                $msg->type = MsgType::TYPE_JOB_DELIVERY;
                $msg->priority = 0;
                $msg->data = json_encode($job);
                $len = $process->write(MessagePacket::encode($msg));
                if (!$len) {
                    $this->log->error('job_tracker.job.send', 'process wirte error, job=' . $msg->data);
                    continue;
                }
            }
            sleep(1);
        }
    }

    /**
     * 主进程消息循环
     */
    public function message_loop() {
        $this->log->trace('job_track.message_loop', 'processing message...');
        $job_msg = $this->queue_provider->consume();
        if (!$job_msg) {
            $this->log->error('job_tracker.message.consume', 'msg consume failed, no data');
            return;
        } 
        if (empty($job_msg->body)) {
            var_dump($job_msg);
        }
        $this->log->trace('job_track.message_loop', 'get message:' . json_encode($job_msg));
        $error_info = MessagePacket::decode($job_msg->body, $msg);
        if ($error_info) {
            $this->log->error('job_tracker.message.decode', 'msg decode error, body=' . $job_msg->body);
            return;
        }
        if (!$this->dispatch_message($msg)) {
            $this->log->error('job_tracker.message.dispatch', 'msg dispatch error, msg=' . json_encode($msg));
            return;
        }
        if (!$this->queue_provider->ack($job_msg)) {
            $this->log->error('job_tracker.message.ack', 'msg ack error, msg=' . json_encode($msg));
            return;
        }
    }

    protected function init_task_buffer($taskid) {
        if (isset($this->fd_buffer_map[$taskid])) {
            return;
        }
        $this->fd_buffer_map[$taskid] = new \bbschedule\lib\buffer\Buffer();
        $this->fd_buffer_map[$taskid]->init(10240);
    }

    /**
     * 处理消息，包括任务调度、任务节点负载上报、任务反馈等
     */
    protected function dispatch_message(Message $msg) {
        if (!$msg) {
            return;
        }
        switch ($msg->type) {
        case MsgType::TYPE_LOAD_REPORT:
            $node = new Node();
            $node->from_json($msg->data);
            $this->workers[$node->to_str()] = $node;
            return TRUE;
        case MsgType::TYPE_JOB_DELIVERY:
            $job_def = new Job_def();
            $job_def->from_json($msg->data);
            
            $job_schedule = new Job_schedule();
            $job_schedule->job_id = $job_def->id;
            $job_schedule->gmt_start = time();
            $job_schedule->gmt_create = time();
            $job_schedule->is_running = 0;
            $job_schedule->concurrent = 0;
            
            if (isset($this->scheduling_jobs[$job_def->id]) && count($this->scheduling_jobs[$job_def->id]) >= $job_def->concurrent) {
                $this->log->info('job_tracker.schedule', "{$job_def->command} is alreay running with {$job_def->concurrent} concurrent");
                $job_schedule->result = "job is alreay run with {$job_def->concurrent} concurrent";
                /*
                $insert_id = $this->job_provider->schedule($job_schedule);
                if (!$insert_id) {
                    $this->log->error('job_tracker.schedule', "schedule {$job_def->command} failed because of db fail");
                    return FALSE;
                }
                 */
                return TRUE;
            }
            
            $job_schedule->job_def = $job_def->to_json();
            $worker = $this->get_worker();
            if (!$worker) {
                $this->log->info('job_tracker.schedule', "no worker available, job=$job_def->command");
                $job_schedule->result = 'no worker available';
                $insert_id = $this->job_provider->schedule($job_schedule);
                if (!$insert_id) {
                    $this->log->error('job_tracker.schedule', "schedule {$job_def->command} failed because of db fail");
                    return FALSE;
                }
                return TRUE;
            }
            $job_schedule->worker = $worker->to_json();
            $this->log->info('job_tracker.schedule', "delivery job {$job_def->command} to worker {$worker->to_str()}, ts={$worker->ts}");
            $insert_id = $this->job_provider->schedule($job_schedule);
            if (!$insert_id) {
                $this->log->error('job_tracker.schedule', "schedule {$job_def->command} failed because of db fail");
                return FALSE;
            }
            $job_schedule->id = $insert_id;
            $client = new \swoole_client(SWOOLE_SOCK_TCP);
            if (!$client->connect($worker->ip, $worker->port, 1)) {
                $this->log->error('job_tracker.schedule', "connect worker failed, ip=$worker->ip, port=$worker->port, job=$job_def->command");
                $job_schedule->result = "connect worker failed, ip=$worker->ip, port=$worker->port";
            }
            $msg_to_send = $msg;
            $msg_to_send->data = json_encode($job_schedule);
            if (!$client->send(MessagePacket::encode($msg_to_send))) {
                $this->log->error('job_tracker.schedule', "send packet to worker failed, ip=$worker->ip, port=$worker->port, job=$job_def->command");
                $job_schedule->result = "send packet to worker failed, ip=$worker->ip, port=$worker->port";
            }
            $client->close();
            $affected_rows = $this->job_provider->feedback($job_schedule);
            if (!$affected_rows) {
                $this->log->error('job_tracker.schedule', "update job schedule {$job_def->command} failed because of db fail");
            }
            $job_schedule->is_running = 1;
            $job_schedule->concurrent += 1;
            $this->scheduling_jobs[$job_def->id][$job_schedule->id] = $job_schedule;
            return TRUE;
        case MsgType::TYPE_JOB_FEEDBACK:
            $job_schedule = new Job_schedule();
            $job_schedule->from_json($msg->data);
            if (!isset($this->scheduling_jobs[$job_schedule->job_id][$job_schedule->id])) {
                $this->log->error('job_tracker.job_feedback', 'error, job not exist, job=' . $job_schedule->to_json());
                return TRUE;
            }
            $affected_rows = $this->job_provider->feedback($job_schedule);
            if (!$affected_rows) {
                $this->log->error('job_tracker.feedback', "feedback job schedule {$job_def->command} failed because of db fail");
                return FALSE;
            }
            if (!$job_schedule->is_running) {
                $this->log->trace('job_tracker.feedback', 'unset job schedule:' . $job_schedule->to_json());
                unset($this->scheduling_jobs[$job_schedule->job_id][$job_schedule->id]);
            }
            return TRUE;
        case MsgType::TYPE_LOG:
            $job_log = new Job_log();
            $job_log->from_json($msg->data);
            if (!isset($this->scheduling_jobs[$job_log->job_id][$job_log->schedule_id])) {
                $this->log->warn('job_tracker.log', 'job not exists, log=' . $msg->data);
            }
            $insert_id = $this->job_provider->log($job_log);
            if (!$insert_id) {
                $this->log->error('job_tracker.log', 'insert job log error, body=' . $msg->data);
            }
            return TRUE;
        default:
            break;
        }
        return TRUE;
    }

    public function get_worker() {
        foreach ($this->workers as $key => $worker) {
            if (time() - $worker->ts > 30) {
                echo $worker->to_str() . " is disconnected\n";
                unset($this->workers[$key]);
            }
        }
        reset($this->workers);
        $len = count($this->workers);
        if ($len == 0) {
            return FALSE;
        }
        $index = rand(0, $len - 1);
        $workers = array_values($this->workers);
        return $workers[$index];
    }

    public function on_start($serv) {
        swoole_set_process_name('JobServer-master');
        $this->log->info('job_tracker', 'server process(' . posix_getpid() . ') started on ' . $this->ip . ':' . $this->port);
        $this->start_schedule_process();
        $serv->tick(1000, array($this, 'message_loop'));
        //$serv->after(3000, array($this, 'start_http_process'));
    }

    public function on_worker_start($serv, $worker_id) {
        swoole_set_process_name('JobServer-worker');
        $this->log->info('job_tracker', 'worker process(' . posix_getpid() . ') started');
    }

    public function on_manager_start($serv) {
        swoole_set_process_name('JobServer-manager');
        $this->log->info('job_tracker', 'manager process(' . posix_getpid() . ') started');
    }

    public function on_receive(\swoole_server $server, $fd, $from_id, $data) {
        if (!MessagePacket::is_packet_complete($data)) {
            $this->init_task_buffer($fd);
            $this->fd_buffer_map[$fd]->append($data);
            return;
        }
        if (isset($this->fd_buffer_map[$fd])) {
            $this->fd_buffer_map[$fd]->append($data);
            $data = $this->fd_buffer_map[$fd]->get();
            $this->fd_buffer_map[$fd]->clear();
        }
        $msg = NULL;
        $errcode = MessagePacket::decode($data, $msg);
        if ($errcode) {
            $this->log->info('job_tracker.on_receive', 'message decode error:' . $data);
            $server->close($fd);
            return;
        }
        $job_msg = new Job_msg();
        $job_msg->body = $data;
        $job_msg->priority = $msg->priority;
        $job_msg->gmt_create = time();
        if (!$this->queue_provider->publish($job_msg)) {
            $this->log->info('job_tracker.msg.publish', 'error to pubilsh msg, data=' . json_encode($msg));
            return;
        }
        $server->close($fd);
    }

    public function on_close(\swoole_server $server, $fd, $from_id) {
        if (isset($this->fd_buffer_map[$fd])) {
            $this->fd_buffer_map[$fd]->destroy();
            $this->fd_buffer_map[$fd] = NULL;
        }
        echo "client {$fd} closed\n";
    }

    public function start() {
        if (!$this->server->start()) {
            $errno = swoole_errno();
            $errmsg = swoole_strerror($errno);
            echo "server started failed, errno={$errno}, errmsg={$errmsg}\n";
            die;
        }
    }
}
