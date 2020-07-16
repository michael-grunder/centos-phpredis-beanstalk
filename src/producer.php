<?php
require __DIR__ . '/vendor/autoload.php';

require_once(__DIR__ . '/common.php');
require_once(__DIR__ . '/job.php');

use Pheanstalk\Pheanstalk;

$obj_opt = getOptions();
$i_seq = 1;
$str_type = $obj_opt->_replica ? 'replica' : 'primary';

$pheanstalk = Pheanstalk::create('127.0.0.1');

do {
    $obj_job = $obj_opt->getNextJob($i_seq);
    echo "[$i_seq]: {$obj_opt->_chan}, type: $str_type => {$obj_job->getCommandCount()} commands\n";

    $pheanstalk
        ->useTube($obj_opt->_chan)
        ->put(
            serialize($obj_job),
            Pheanstalk::DEFAULT_PRIORITY,
            $obj_opt->_delay,
            $obj_opt->_retry
         );

    $obj_opt->sleep_update();
    $i_seq++;
} while (true);
