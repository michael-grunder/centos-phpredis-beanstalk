<?php

require_once(__DIR__ . '/common.php');

$obj_r = getLocalCon();

$arr_kv = $obj_r->hGetAll('countdown');

echo str_pad('PID', 8, ' ', STR_PAD_LEFT) . ' ' .
     str_pad('HMS', 10, ' ', STR_PAD_LEFT) . "\n";

foreach ($arr_kv as $str_pid => $str_hms) {
    echo str_pad($str_pid, 8, ' ', STR_PAD_LEFT) . ' ' .
         str_pad($str_hms, 10, ' ', STR_PAD_LEFT) . "\n";
}
