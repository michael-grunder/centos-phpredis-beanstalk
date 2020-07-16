<?php
require __DIR__ . '/vendor/autoload.php';
require_once('common.php');

use Pheanstalk\Pheanstalk;

$obj_opt = getOptions();
$obj_r = getConObj($obj_opt);
$primary = isPrimary($obj_r);
$mode = $obj_opt->_mode;

echo "Listening to job queue: {$obj_opt->_chan}\n";
echo "Redis: " . $obj_r->getHost() . ':' . $obj_r->getPort() . "\n";
echo "Exec Mode: $mode\n";

$pheanstalk = Pheanstalk::create('127.0.0.1');

// we want jobs from 'testtube' only.
$pheanstalk->watch($obj_opt->_chan);

while (true) {
    // this hangs until a Job is produced.
    $job = $pheanstalk->reserve();

    try {
        $obj_job = @unserialize($job->getData());
        if ( ! ($obj_job InstanceOf MockJob)) {
            echo "Malformed payload, skipping...";
            continue;
        }

        $id = $obj_job->id();
        $delay = time() - $obj_job->timestamp();
        $ccount = $obj_job->getCommandCount();
        $mode = $obj_opt->getMode();

        $st = microtime(true);
        $arr_rv = $obj_job->run($obj_r, $obj_opt->_mode);
        $msec = round((microtime(true) - $st) * 1000);

        $arr_disp = [];
        foreach ($arr_rv as $cmd => $rv) {
            $arr_disp[] = "$cmd: $rv";
        }
        $str_disp = implode(', ', $arr_disp);

        echo "[$id] - {$delay}s ago, $ccount cmds, {$msec}ms => [$mode; $str_disp]\n";

        $pheanstalk->delete($job);
    }
    catch(\Exception $e) {
        echo "[$id]: Exception: " . $e->getMessage() . "\n";
        if ($obj_opt->_discard_failures) {
            $pheanstalk->delete($job);
        } else {
            $pheanstalk->release($job);
        }
    }
}
