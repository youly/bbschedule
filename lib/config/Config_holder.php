<?php namespace bbschedule\lib\config;

class Config_holder {
    private $config;

    public function __construct($file) {
        if (file_exists($file)) {
            $this->config = parse_ini_file($file, TRUE);
        }
    }
    
    public function get($section, $key) {
        if (isset($this->config[$section]) && isset($this->config[$section][$key])) {
            return $this->config[$section][$key];
        }
        return NULL;
    }
    
    public function get_section($section) {
        if (isset($this->config[$section])) {
            return $this->config[$section];
        }
        return [];
    }

    public static function get_instance() {
        if (!defined('ROOT_PATH')) {
            return NULL;
        }
        $config_file = ROOT_PATH . 'config/config.ini';
        if (!file_exists($config_file)) {
            return NULL;
        }
        return new Config_holder($config_file);
    }
}
