<?php namespace bbschedule\provider\logprovider;

class File_log_provider extends Abstract_log_provider {

    protected $file;
    protected $level;
    protected $servername;
    protected $pid;
    
    /**
     * 日志格式
     * Y-m-d H:i:s|level|servername|pid|name|message
     */
    protected $format = '%s|%s|%s|%s|%s|%s';
    
    public function __construct($config) {
        if (empty($config['log_path'])) {
            die("log path not exists");
        }
        if (empty($config['log_level'])) {
            die("log level not exists");
        }
        if (empty($config['file'])) {
            die("log file name not exists");
        }
        $this->servername = gethostname();
        $this->pid = posix_getpid();
        $this->file = $config['file'];
    }

    public function set_file($file) {
        $this->file = $file;
    }

    protected function add($action, $msg, $level) {
        $msg = strtr($msg, array("\n" => '', "\r" => '', '\n' => '', '\r' => '', '|' => '')); 
        $message = sprintf($this->format, date('Y-m-d H:i:s'), self::$desc_level[$level], $this->servername, $this->pid, $action, $msg);
        $message .= "\n";
        @file_put_contents($this->file, $message, FILE_APPEND);
    } 

    public function error($action, $msg) {
        $this->add($action, $msg, self::ERROR);
    }
    
    public function warn($action, $msg) {
        $this->add($action, $msg, self::WARN);
    }
    
    public function info($action, $msg) {
        $this->add($action, $msg, self::INFO);
    }
    
    public function debug($action, $msg) {
        $this->add($action, $msg, self::DEBUG);
    }
    
    public function trace($action, $msg) {
        $this->add($action, $msg, self::TRACE);
    }

    public function __call($method, $params) {
        $level_desc = array_flip(self::$desc_level);
        $desc = strtoupper($method);
        if (!isset($level_desc[$desc])) {
            throw new \Exception('method not exists');
        }
        if ($level_desc[$desc] < $this->level) {
            return;
        }
        return call_user_func_array(array($this, $method), $params);
    }
}
