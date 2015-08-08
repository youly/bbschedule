<?php namespace bbschedule\lib\common;

class Mysqli {
    private $db;

    private $host;
    private $port;
    private $user;
    private $pass;
    private $database;
    private $charset;

    public function __construct($config) {
        if (!is_array($config)) {
            die("mysqli config error\n");
        }
        $config_check_arr = array('host', 'port', 'user', 'pass', 'database', 'charset');
        foreach ($config_check_arr as $key) {
            if (empty($config[$key])) {
                die("mysqli config $key not exists\n");
            }
            $this->$key = $config[$key];
        }

        $this->db = FALSE;
    }

    public function connect() {
        if (!$this->db) {
            $this->db = mysqli_init();
        }
        if (!$this->db->real_connect($this->host, $this->user, $this->pass, $this->database, $this->port, NULL, 0)) {
            $errno = $this->err_no();
            $errmsg = $this->err_msg();
            trigger_error("connect database error, errno=$errno, errmsg=$errmsg", E_USER_ERROR);
            return FALSE;
        }
        if ($this->db->set_charset($this->charset)) {
            return TRUE;
        }
        return FALSE;
    }

    public function close() {
        if (!$this->db) {
            return TRUE;
        }
        if (mysqli_close($this->db)) {
            $this->db = FALSE;
            return TRUE;
        }
        return FALSE;
    }

    public function execute($sql) {
        if (!$this->db) {
            $this->connect();
        }
        if (!$res = mysqli_query($this->db, $sql)) {
            $errno = $this->err_no();
            $errmsg = $this->err_msg();
            trigger_error("query database error, errno=$errno, errmsg=$errmsg", E_USER_ERROR);
            return FALSE;
        }
        return $res;
    }

    public function get_one_row($sql) {
        $res = $this->execute($sql);
        if (!$res) {
            return NULL;
        }
        return mysqli_fetch_assoc($res);
    }

    public function get_multi_row($sql) {
        $res = $this->execute($sql);
        if (!$res) {
            return [];
        }
        $result = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $result[] = $row;
        }
        return $result;
    }

    public function get_one($sql) {
        $res = $this->execute($sql);
        if (!$res) {
            return NULL;
        }
        $row = mysqli_fetch_row($res);
        return $row[0];
    }

    public function get_one_cell($sql) {
        $res = $this->execute($sql);
        if (!$res) {
            return [];
        }
        $result = [];
        while ($row = mysqli_fetch_row($res)) {
            $result[] = $row[0];
        }
        return $result;
    }

    public function err_no() {
        return mysqli_errno($this->db);
    }

    public function err_msg() {
        return mysqli_error($this->db);
    }

    public function affected_rows() {
        return mysqli_affected_rows($this->db);
    }

    public function insert_id() {
        return mysqli_insert_id($this->db);
    }
}
