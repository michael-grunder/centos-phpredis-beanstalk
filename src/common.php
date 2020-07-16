<?php

require_once(__DIR__ . '/job.php');

function isPrimary($obj_r) {
    $info = $obj_r->info();
    return $info['role'] == 'master';
}

function panicAbort($msg) {
    fprintf(STDERR, "Error:  $msg\n");
    exit(-1);
}



function getCon($host, $port, $auth) {
    $obj_r = new Redis();

    if ($auth) {
        $obj_r->connect($host, $port, 1, null, 0, 0, ['auth' => $auth]);
    } else {
        $obj_r->connect($host, $port, 1, null, 0, 0);
    }

    if ( ! $obj_r->isConnected()) {
        if ($obj_r->getLastError()) {
            echo "Redis Error:  {$obj_r->getLastError()}\n";
        }
        panicAbort("Can't connect to Redis at $host:$port");
    }

    return $obj_r;
}

function getConObj($opt) {
    if ( ! ($opt InstanceOf HostInfo) && ! ($opt InstanceOf Options))
        panicAbort("Must pass an Options or HostInfo object");

    return getCon($opt->_host, $opt->_port, $opt->_auth);
}

class HostInfo {
    public $_host;
    public $_port;
    public $_auth;

    public function __construct($host, $port, $auth) {
        $this->_host = $host;
        $this->_port = $port;
        $this->_auth = $auth;
    }

    public static function load($str_file) {
        if ( ! is_file($str_file))
            panicAbort("Can't load host info file '$str_file'");

        $arr_lines = array_filter(explode("\n", file_get_contents($str_file)));

        $arr_fields = ['host' => NULL, 'port' => NULL, 'auth' => NULL];

        foreach ($arr_lines as $str_line) {
            $arr_bits = explode('=', $str_line);
            if (count($arr_bits) != 2)
                panicAbort("Malformed info line '$str_line'");

            list($str_key, $str_val) = $arr_bits;
            $arr_fields[trim($str_key)] = trim($str_val);
        }

        if ( ! $arr_fields['host'] || ! $arr_fields['port'])
            panicAbort("We must have at least host and port (file: $str_file)");

        return new HostInfo($arr_fields['host'], $arr_fields['port'], $arr_fields['auth']);
    }
}

class Options {
    public $_host;
    public $_port;
    public $_auth;
    public $_chan;
    public $_sleep_min;
    public $_sleep_max;
    public $_min_cmds;
    public $_max_cmds;
    public $_sets;
    public $_fields;
    public $_delay;
    public $_retry;
    public $_mode;
    public $_replica;
    public $_discard_failures;

    public function __construct($obj_info, $chan, $mode, $replica, $sleep_min, $sleep_max,
                                $min_cmds, $max_cmds, $sets, $fields, $delay,
                                $retry, $discard)
    {
        $this->_host = $obj_info->_host;
        $this->_port = $obj_info->_port;
        $this->_auth = $obj_info->_auth;
        $this->_chan = $chan;
        $this->_sleep_min = $sleep_min;
        $this->_sleep_max = $sleep_max;
        $this->_min_cmds = $min_cmds;
        $this->_max_cmds = $max_cmds;
        $this->_sets = $sets;
        $this->_fields = $fields;
        $this->_delay = $delay;
        $this->_retry = $retry;
        $this->_mode = $mode;
        $this->_replica = $replica;
        $this->_discard_failures = $discard;
    }

    public function sleep_us() {
        return rand($this->_sleep_min, $this->_sleep_max);
    }

    protected static function waitForKey($usec) {
        $boo_key_pressed = false;
        $rss = array(STDIN);
        $wss = $ess = null;

        stream_set_blocking(STDIN, 0);
        while (stream_select($rss, $wss, $ess, 0, $usec)) {
            $boo_key_pressed = true;
            break;
        }
        stream_set_blocking(STDIN, 1);
        if ($boo_key_pressed) {
            fgets(STDIN);
        }

        return $boo_key_pressed;
    }

    public static function sleepWithUpdate($usec, $tick_usec = 100000) {
        $last_remaining = NULL;

        while ($usec > 0) {
            $remaining = gmdate("H:i:s", round($usec / 1000000));

            if ($remaining != $last_remaining) {
                echo "Sleeping " . str_pad($remaining, 10, ' ', STR_PAD_LEFT) . " (press return to exit early)\r";
            }

            $n = $usec > $tick_usec ? $tick_usec : $usec;
            if (self::waitForKey($n)) {
                echo "Detected keypress, exiting early";
                break;
            }

            $usec -= $n;
            $last_remaining = $remaining;
        }
        echo "\n";
    }

    public function sleep_update() {
        self::sleepWithUpdate($this->sleep_us(), 100000, '/tmp/go');
    }

    public function payload() {
        $n = rand($this->_min_fields, $this->_max_fields);
        $a = [];

        for ($i = 0; $i < $n; $i++) {
            $a["mem:$i"] = "val:$i";
        }

        return $a;
    }

   public function getNextJob($id) {
       $i_cmds = rand($this->_min_cmds, $this->_max_cmds);
       return new MockJob($id, $this->_replica, $i_cmds, $this->_sets, $this->_fields);
   }

    public function getMode() {
        static $_arr_modes = ['multi', 'pipe', 'multipipe', 'atomic'];

        if ($this->_mode == 'random' || $this->_mode == 'rand') {
            return $_arr_modes[array_rand($_arr_modes)];
        } else {
            return $this->_mode;
        }
    }
}

function extractRange($str) {
    $bits = explode('-', $str);
    if (count($bits) == 2) {
        return [$bits[0], $bits[1]];
    } else {
        return [$bits[0], $bits[0]];
    }
}

function getNumericOption($opt, $k, $default) {
    if ( ! isset($opt[$k]))
        return $default;

    if ( ! is_numeric($opt[$k])) {
        echo "Warning:  Option '$k' must be numeric, defaulting to $default\n";
        return $default;
    }

    return $opt[$k];
}

function getRangeOption($opt, $k, $dmin, $dmax) {
    if ( ! isset($opt[$k]))
        return [$dmin, $dmax];

    list($min, $max) = extractRange($opt[$k]);
    return $max < $min ? [$max, $min] : [$min, $max];
}

function printUsage() {
    global $argv;

    echo "Usage: php {$argv[0]} [OPTIONS]\n\n";
    echo "  --channel  <beanstalkd queue name>\n";
    echo "  --instance <redis host, port, auth file>\n";
    echo "  --commands redis command count or range (default: 1)\n";
    echo "  --mode     How to deliver commands (default: atomic)\n";
    echo "             valid: atomic, pipe, multi, multipipe\n";
    echo "  --fields   How many fields to interact with when sending HASH commans (default: 100)\n";
    echo "             examples: --commands 50; --commands 1-20\n";
    echo "  --sleep    delay between job prodution or range (default: 1s)\n";
    echo "             examples: --sleep 1; --sleep 1-900\n";
    echo "  --delay    beanstalkd delivery delay (default: 0)\n";
    echo "  --retry    beanstalkd retry value (default: 30)\n";
    echo "  --discard  Tell the consumer to delete messages that cause an exception (e.g. trying to write\n";
    echo "             to a read-only replica.  This can be useful to clear the queue.)\n";

    echo "\n Alternative to using an --instance file: \n\n";
    echo "  --host <redis host> (default: localhost)\n";
    echo "  --port <redis port> (default: 6379)\n";
    echo "  --auth <redis auth> (default: NULL or no auth)\n";
    exit(-1);
}

function getOptions() {
    $opt = getopt('h', ['instance:', 'host:', 'port:', 'auth:', 'channel:',
                  'sleep:', 'sets:', 'fields:', 'delay:', 'retry:',
                  'commands:', 'mode:', 'discard', 'help']);

    if (isset($opt['h']) || isset($opt['help']))
        printUsage();

    $chan = $opt['channel'] ?? NULL;
    $inst = $opt['instance'] ?? '';
    $host = $opt['host'] ?? '';
    $port = $opt['port'] ?? '';
    $auth = $opt['auth'] ?? NULL;
    list($cmd_min, $cmd_max) = getRangeOption($opt, 'commands', 1, 10);
    list($sleep_min, $sleep_max) = getRangeOption($opt, 'sleep', 1, 1);
    $sets = $opt['sets'] ?? 100;
    $fields = getNumericOption($opt, 'fields', 100);
    $delay = $opt['delay'] ?? 0;
    $retry = $opt['retry'] ?? 30;
    $mode = $opt['mode'] ?? 'atomic';
    $discard = isset($opt['discard']);

    MockJob::validateMode($mode);

    if ( ! is_numeric($fields)) {
        echo "Warning:  Fields must be numeric, defaulting to 100\n";
        $fields = 100;
    }

    /* Convert sleep range to usec */
    $sleep_min *= 1000000;
    $sleep_max *= 1000000;

    if ($inst) {
        $obj_info = HostInfo::load($inst);
    } else if ($host && $port) {
        $obj_info = new HostInfo($host, $port, $auth);
    } else {
        $obj_info = new HostInfo('localhost', 6379, NULL);
    }

    if (!$chan) $chan = 'default';

    $obj_r = getConObj($obj_info);
    $info = $obj_r->info();
    $replica = $info['role'] != 'master';

    return new Options($obj_info, $chan, $mode, $replica, $sleep_min, $sleep_max, $cmd_min, $cmd_max,
                       $sets, $fields, $delay, $retry, $discard);
}
