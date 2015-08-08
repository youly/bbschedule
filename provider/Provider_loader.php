<?php namespace bbschedule\provider;

use \bbschedule\lib\config\Config_holder;

class Provider_loader {

    private static $config_holder;

    public static function get($provider_name) {
        $provider_impl = self::discover_provider($provider_name);
        if (is_null($provider_impl)) {
            return NULL;
        }
        $provider_class = '\\bbschedule\provider\\' . $provider_name . '\\' . $provider_impl;
        $provider_config = self::provider_config($provider_impl);
        return new $provider_class($provider_config);
    }

    public static function set_config_holder($config_holder) {
        self::$config_holder = $config_holder;
    }

    private static function provider_config($provider_name) {
        if (empty(self::$config_holder)) {
            $config_holder = Config_holder::get_instance();
            if (is_null($config_holder)) {
                die("config file not exists\n");
            }
            self::$config_holder = $config_holder;
        }
        return self::$config_holder->get_section(strtolower($provider_name));
    }

    private static function discover_provider($provider_name) {
        if (empty(self::$config_holder)) {
            $config_holder = Config_holder::get_instance();
            if (is_null($config_holder)) {
                die("config file not exists\n");
            }
            self::$config_holder = $config_holder;
        }
        $common_config = self::$config_holder->get_section('common');
        if (isset($common_config[$provider_name])) {
            return ucfirst($common_config[$provider_name]);
        }
        return NULL;
    }
}
