<?php namespace bbschedule\provider\logprovider;

abstract class Abstract_log_provider implements Log_provider {
    const ERROR = 100;
    const WARN = 80;
    const INFO = 60;
    const DEBUG = 40;
    const TRACE = 20;

    public static $desc_level = [
        self::ERROR => 'ERROR',
        self::WARN => 'WARN',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG',
        self::TRACE => 'TRACE',
    ];
}
