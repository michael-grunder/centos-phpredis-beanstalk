<?php
require __DIR__ . '/vendor/autoload.php';
require_once('common.php');

use Pheanstalk\Pheanstalk;

$obj_opt = getOptions();
$obj_r = getConObj($obj_opt);
$primary = isPrimary($obj_r);
$mode = $obj_opt->_mode;

$host = $obj_r->getHost();
$port = $obj_r->getPort();

logMessage("consumer", "{Redis: '$host:$port', mode: $mode}");

$pheanstalk = Pheanstalk::create('127.0.0.1');

// we want jobs from 'testtube' only.
$pheanstalk->watch($obj_opt->_chan);

while (true) {
    // this hangs until a Job is produced.
    $job = $pheanstalk->reserve();

    try {
        $data = $job->getData();
        $obj_job = @unserialize($data);
        if ( ! ($obj_job InstanceOf MockJob)) {
            logMessage("consumer", "Malformed payload, skipping...");
            if ($obj_opt->_debug);
                logMessage("consumer", serialize($data));
            if ($obj_opt->_discard_failures)
                $pheanstalk->delete($job);
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

        logMessage("consumer", "{id: $id, delay: {$delay}, commands: $ccount cmds, mstime: {$msec}} [$mode; $str_disp]");

        $pheanstalk->delete($job);
    }
    catch(\Exception $e) {
        logMessage("consumer", "{id: $id, Exception: {$e->getMessage()}}");
        if ($obj_opt->_discard_failures) {
            $pheanstalk->delete($job);
        } else {
            $pheanstalk->release($job);
        }
    }
}
