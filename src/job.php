<?php

require_once(__DIR__ . '/common.php');

class MockJob {
    private $_id;
    private $_ts;
    private $_arr_cmds = [];
    private $_atomic = true;

    private static $_arr_modes = ['multi', 'pipe', 'multipipe', 'random', 'atomic'];

    public static function validateMode($str_mode) {
        if ($str_mode && !in_array($str_mode, self::$_arr_modes))
            panicAbort("Unknown mode '$str_mode' (valid modes: " . implode(', ', self::$_arr_modes));
    }

    public function timestamp() {
        return $this->_ts;
    }

    public function id() {
        return $this->_id;
    }

    protected function start($obj_r, $str_mode) {
        if ($str_mode == 'pipe') {
            $obj_r->pipeline();
            return false;
        } else if ($str_mode == 'multi') {
            $obj_r->multi();
            return false;
        } else if ($str_mode == 'multipipe') {
            $obj_r->pipeline();
            $obj_r->multi();
            return false;
        }

        return true;
    }

    protected function finish($obj_r, $str_mode) {
        if ($str_mode == 'pipe' || $str_mode == 'multi') {
            return $obj_r->exec();
        } else if ($str_mode == 'multipipe') {
            $obj_r->exec();
            return $obj_r->exec();
        }

        return NULL;
    }

    protected function enqueueResult(&$arr_dst, $str_type, $v) {
        $val = $this->_atomic ? $v : NULL;
        $key = $str_type . ':' . uniqid();
        $arr_dst[$key] = $val;
    }

    protected function calculateResults($arr_ops, $arr_vals) {
        if (count($arr_ops) != count($arr_vals))
            panicAbort("Values and ops should have same number of items");

        $arr_result = [];

        foreach ($arr_ops as $op) {
            $val = array_shift($arr_vals);
            list($cmd,) = explode(':', $op);

            if ( ! isset($arr_result[$cmd])) $arr_result[$cmd] = 0;

            switch ($cmd) {
                case 'get':
                case 'hget':
                    $arr_result[$cmd] += strlen($val);
                    break;
                case 'hset':
                case 'set':
                    $arr_result[$cmd] += $val == true;
                    break;
                case 'hincrby':
                case 'zcard':
                case 'zunionstore':
                    $arr_result[$cmd] += $val;
                    break;
            }
        }

        return $arr_result;
    }

    public function run($obj_r, $str_mode) {
        $this->_atomic = $this->start($obj_r, $str_mode);

        $arr_ops = [];

        foreach ($this->_arr_cmds as $arr_cmd) {
            $str_cmd = array_shift($arr_cmd);

            switch ($str_cmd) {
                case 'get':
                    $key_id = $arr_cmd[0];
                    $rv = $obj_r->get("skey:$key_id");
                    break;
                case 'set':
                    list ($key_id, $val) = $arr_cmd;
                    $rv = $obj_r->set("skey:$key_id", $val);
                    break;
                case 'hget':
                    list ($key_id, $mem_id) = $arr_cmd;
                    $rv = $obj_r->hGet("shash:$key_id", "mem:$mem_id");
                    break;
                case 'hset':
                    list ($key_id, $mem_id, $mem_val) = $arr_cmd;
                    $rv = $obj_r->hSet("shash:$key_id", "mem:$mem_id", $mem_val);
                    break;
                case 'hincrby':
                    list ($key_id, $mem_id, $by) = $arr_cmd;
                    $rv = $obj_r->hIncrBy("ihash:$key_id", "mem:$mem_id", $by);
                    break;
                case 'zunionstore':
                    list ($out, $n) = $arr_cmd;
                    $arr_sets = array_map(function ($n) { return "zkey:$n"; }, range(1, $n));
                    $rv = $obj_r->zUnionStore($out, $arr_sets);
                    break;
                case 'zcard':
                    $id = $arr_cmd[0];
                    $rv = $obj_r->zCard("zkey:$id");
                    break;
                default:
                    panicAbort("Fatal:  Unknown command '$str_cmd'");
                    break;
            }

            $this->enqueueResult($arr_ops, $str_cmd, $rv);
        }

        if ( ! $this->_atomic) {
            $arr_values = $this->finish($obj_r, $str_mode);
            $arr_result = $this->calculateResults(array_keys($arr_ops), $arr_values);
        } else {
            $arr_result = $this->calculateResults(array_keys($arr_ops), array_values($arr_ops));
        }

        return $arr_result;
    }

    public function getCommandCount() {
        return count($this->_arr_cmds);
    }

    protected function getCommands($boo_replica, $i_count, $i_sets, $i_fields) {
        $arr_result = [];

        $arr_cmds = ['get', 'hget', 'zcard'];
        if ( ! $boo_replica) {
            $arr_cmds = array_merge($arr_cmds, ['set', 'hincrby', 'hset', 'zunionstore']);
        }

        for ($i = 0; $i < $i_count; $i++) {
            $str_cmd = $arr_cmds[array_rand($arr_cmds)];
            $id = rand(1, $i_sets);
            switch ($str_cmd) {
                case 'get':
                    $arr_result[] = ['get', $id];
                    break;
                case 'set':
                    $arr_result[] = ['set', $id, uniqid()];
                    break;
                case 'hget':
                    $arr_result[] = ['hget', $id, rand(1, $i_fields)];
                    break;
                case 'hset':
                    $arr_result[] = ['hset', $id, rand(1, $i_fields), uniqid()];
                    break;
                case 'hincrby':
                    $arr_result[] = ['hincrby', $id, rand(1, $i_fields), rand(1, 100)];
                    break;
                case 'zcard':
                    $arr_result[] = ['zcard', $id];
                    break;
                case 'zunionstore':
                    $sets = rand(1, 24);
                    $arr_result[] = ['zunionstore', 'out', $sets];
                    break;
            }
        }

        return $arr_result;
    }

    public function __construct($id, $boo_replica, $i_cmds, $i_sets, $i_fields) {
        $this->_id = $id;
        $this->_ts = time();
        $this->_arr_cmds = $this->getCommands($boo_replica, $i_cmds, $i_sets, $i_fields);
    }
}
