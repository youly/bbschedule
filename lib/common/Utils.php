<?php namespace bbschedule\lib\common;

class Utils {
    /**
     * 获取系统负载
     */
    public static function get_sys_load() {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        return array();
    }

    /**
     * 转义特殊字符
     */
    public static function add_slashes($fields) {
        if (!is_array($fields)) {
            return addslashes($fields);
        }
        $_fields = [];
        foreach ($fields as $field) {
            $_fields[] = addslashes($field);
        }
        return $_fields;
    }
}
