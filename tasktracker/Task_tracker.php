<?php namespace bbschedule\tasktracker;

use \bbschedule\provider\Provider_loader;
use \bbschedule\lib\domain\Job_def;
use \bbschedule\lib\domain\Job_schedule;
use \bbschedule\lib\domain\Job_log;
use \bbschedule\lib\domain\Node;
use \bbschedule\lib\protocol\MessagePacket;
use \bbschedule\lib\protocol\Message;
use \bbschedule\lib\protocol\MsgType;
use \bbschedule\lib\common\Utils;

class Task_tracker {
    
    protected $ip;
    protected $port;
    protected $job_server_ip;
    protected $job_server_port;
    
    protected $server;
    protected $log;

    public function __construct($config) {
        $config_check_arr = array('ip', 'port', 'job_server_ip', 'job_server_port');
        foreach ($config_check_arr as $key) {
            if (empty($config[$key])) {
                die("task tracker config $key not exists\n");
            }
            $this->$key = $config[$key];
        }
        $this->init_swoole_server();
        $this->log = Provider_loader::get('logprovider');
    }

    protected function init_swoole_server() {
        $server = new \swoole_server($this->ip, $this->port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $server->set([
            'task_worker_num' => 4,
        ]);
        $server->on('Start', array($this,'on_start'));
        $server->on('WorkerStart', array($this,'on_worker_start'));
        $server->on('Receive', array($this,'on_receive'));
        $server->on('Close', array($this,'on_close'));
        $server->on('Task', array($this, 'on_task'));
        $server->on('Finish', array($this, 'on_finish'));
        $this->server = &$server;
    }

    public function on_task(\swoole_server $serv, $task_id, $from_id, $msg) {
    }

    public function on_finish(\swoole_server $serv, $task_id, $data) {
    }

    public function run_command($msg) {
        $job_schedule = new Job_schedule();
        $job_schedule->from_json($msg->data, FALSE);
        $job_def = new Job_def();
        $job_def->from_json($job_schedule->job_def);
        echo "get command, ", json_encode($job_def), "\n";
        $this->log->info('task_tracker', "get command: $job_def->command");
        $process = new \swoole_process(function($process) use ($job_def) {
            $command = trim($job_def->command);
            $args = preg_split('/\s+/', $command);
            $bin = array_shift($args);
            $process->exec($bin, $args);
        }, TRUE);
        $job_schedule->pid = $process->start();
        swoole_event_add($process->pipe, function ($pipe) use ($process, $job_schedule) {
            $data = $process->read();
            $client = new \swoole_client(SWOOLE_SOCK_TCP);
            if (!$client->connect($this->job_server_ip, $this->job_server_port)) {
                $this->log->error('task_tracker', "job server is not available");
                return;
            }
            $job_log = new Job_log();
            $job_log->schedule_id = $job_schedule->id;
            $job_log->job_id = $job_schedule->job_id;
            $job_log->pid = $job_schedule->pid;
            $job_log->log = $data;
            $job_log->gmt_create = time();
            
            $msg = new Message();
            $msg->type = MsgType::TYPE_LOG;
            $msg->priority = 0;
            $msg->data = $job_log->to_json(); 
            $len = $client->send(MessagePacket::encode($msg));
            if (!$len) {
                $this->log->error('task_tracker', "send log to job server failed");
                return;
            }
            $client->close();
        });
        
        $result = $process->wait();
        $job_schedule->gmt_end = time();
        $job_schedule->is_running = 0;
        if ($result) {
            $job_schedule->exit_code = $result['code'];
            $job_schedule->result = isset($result['errmsg']) ? $result['errmsg'] : '';
        } else {
            $job_schedule->result = 'wait process failed';
        }
        
        $feedback = new Message();
        $feedback->type = MsgType::TYPE_JOB_FEEDBACK;
        $feedback->priority = 0;
        $feedback->data = $job_schedule->to_json();
        
        $client = new \swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect($this->job_server_ip, $this->job_server_port)) {
            $this->log->error('task_tracker', "job server is not available");
            return;
        }
        $len = $client->send(MessagePacket::encode($feedback));
        if (!$len) {
            $this->log->error('task_tracker', "send feedback job server failed, data=$feedback->data");
            return;
        }
        $client->close();
    }

    protected function init_task_buffer($taskid) {
        if (isset($this->fd_buffer_map[$taskid])) {
            return;
        }
        $this->fd_buffer_map[$taskid] = new \bbschedule\lib\buffer\Buffer();
        $this->fd_buffer_map[$taskid]->init(10240);
    }

    /**
     * 处理来自服务端的消息
     */
    protected function dispatch_message($server, $fd, $msg) {
        switch ($msg->type) {
        case MsgType::TYPE_JOB_DELIVERY:
            $this->run_command($msg);
            break;
        default:
            break;
        }
    }

    public function load_report() {
        $client = new \swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect($this->job_server_ip, $this->job_server_port)) {
            $this->log->error('task_tracker', "job server is not available");
            return;
        }
        $worker = new Node();
        $worker->type = Node::TYPE_TASK_SERVER;
        $worker->name = gethostname();
        $worker->ip = $this->ip;
        $worker->port = $this->port;
        $worker->load = json_encode(Utils::get_sys_load());
        $worker->ts = time(); 
        
        $msg = new Message();
        $msg->type = MsgType::TYPE_LOAD_REPORT;
        $msg->priority = 0;
        $msg->data = $worker->to_json();
        $len = $client->send(MessagePacket::encode($msg));
        if (!$len) {
            $this->log->error('task_tracker', "send load job server failed, data=$msg->data");
            return;
        }
        $client->close();
    }

    public function on_start($serv) {
        swoole_set_process_name('TaskServer-master');
        $this->log->info('task_tracker', "server started on {$this->ip}:{$this->port}");
        $serv->tick(1000, array($this, 'load_report'));
    }

    public function on_worker_start($serv, $workder_id) {
        swoole_set_process_name('TaskServer-workder');
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
            $this->log->error('task_tracker.on_receive', "message decode error:{$data}");
            $server->close($fd);
            return;
        }
        $this->dispatch_message($server, $fd, $msg);
    }

    public function on_close(\swoole_server $server, $fd, $from_id) {
        if(isset($this->fd_buffer_map[$fd])){
            $this->fd_buffer_map[$fd]->destroy();
            $this->fd_buffer_map[$fd] = NULL;
        }
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
