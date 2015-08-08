<?php
namespace bbschedule\jobtracker;

class HttpServer {

    protected $ip;
    protected $port;
    protected $job_server_ip;
    protected $job_server_port;
    protected $server;

    protected $route = [
        '/running_jobs' => 'get_running_jobs',
        '/add_job' => 'add_job',
        '/del_job' => 'del_job',
        '/kill_job' => 'kill_job',
    ];
    
    public function __construct($config) {
        $config_arr = ['ip', 'port', 'job_server_ip', 'job_server_port'];
        foreach ($config_arr as $key) {
            if (!isset($config[$key])) {
                die("config $key not exist\n");
            }
            $this->$key = $config[$key];
        }
    }

    public static function run($options) {
        $config_file = ROOT_PATH . 'config/config.ini';
        if (!empty($options['c'])) {
            $config_file = $options['c'];
        }
        if (!file_exists($config_file)) {
            die("config file {$config_file} not exist\n");
        }
        $configHolder = new \bbschedule\lib\config\ConfigHolder($config_file);
        $config_server = $configHolder->get_section('common');
        $config_server = array_merge($config_server, $configHolder->get_section('httpserver'));
        if (!empty($options['p'])) {
            $config_server['port'] = $options['p'];
        }
        if (!empty($options['h'])) {
            $config_server['ip'] = $options['h'];
        }
        $server = new HttpServer($config_server);
        $server->start();
    }

    public function start() {
        $this->server = new \swoole_http_server($this->ip, $this->port);
        $this->server->on('request', array($this, 'index'));
        $this->server->start();
    }

    public function index($request, $response) {
        $uri = $request->server[strtolower('PATH_INFO')];
        foreach ($this->route as $path => $method) {
            $pattern = str_replace("/", '\/', $path);
            preg_match("/$pattern/", $uri, $matches);
            if (empty($matches)) {
                continue;
            }
            call_user_func_array(array($this, $method), [$request, $response]);
            return;
        }
        $response->status(404);
        $response->write('not found');
        $response->end();
        return;
    }

    public function get_running_jobs($req, $res) {
        $res->status(200);
        $res->write('found');
        $res->end();
    }
    public function add_job($req, $res) {
        $res->status(200);
        $res->write('found');
        $res->end();
    }
    public function del_job($req, $res) {
        $res->status(200);
        $res->write('found');
        $res->end();
    }
    public function kill_job($req, $res) {
        $res->status(200);
        $res->write('found');
        $res->end();
    }

}
