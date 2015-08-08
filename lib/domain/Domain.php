<?php namespace bbschedule\lib\domain;

class Domain extends \stdClass {

    public function from_arr($arr, $ignore_unknown_prop = TRUE) {
        $class = get_called_class();
        foreach ($arr as $key => $val) {
            if (!property_exists($class, $key) && $ignore_unknown_prop) {
                continue;
            }
            $this->$key = $val;
        }
    }

    public function to_arr($ignore_unknown_prop = TRUE) {
        $arr = [];
        $class = get_called_class();
        foreach ($this as $key => $val) {
            if (!property_exists($class, $key) && $ignore_unknown_prop) {
                continue;
            }
            $arr[$key] = $val;
        }
        return $arr;
    }

    public function from_json($json, $ignore_unknown_prop = TRUE) {
        $arr = json_decode($json, TRUE);
        $this->from_arr($arr, $ignore_unknown_prop);
    }

    public function to_json($ignore_unknown_prop = TRUE) {
        $arr = $this->to_arr($ignore_unknown_prop);
        return json_encode($arr);
    }
}
