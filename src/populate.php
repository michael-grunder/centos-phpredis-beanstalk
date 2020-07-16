<?php

require_once(__DIR__ . '/common.php');

$obj_opt = getOptions();
$obj_r = getConObj($obj_opt);

$st = microtime(true);

for ($i = 0; $i < $obj_opt->_sets; $i++) {
    $obj_r->set("skey:$i", uniqid());

    $arr_i_kvs = [];
    $arr_s_kvs = [];
    $arr_z_kvs = ["zkey:$i"];

    for ($j = 0; $j < $obj_opt->_fields; $j++) {
        $arr_i_kvs["mem:$j"] = rand(1, 100);
        $arr_s_kvs["mem:$j"] = uniqid();
        $arr_z_kvs[] = rand(1, 100);
        $arr_z_kvs[] = "mem:$j";
    }

    $obj_r->hmset("shash:$i", $arr_s_kvs);
    $obj_r->hmset("ihash:$i", $arr_i_kvs);

    call_user_func_array([$obj_r, 'zAdd'], $arr_z_kvs);
}

$tm = round(microtime(true) - $st, 2);

$real_count = $obj_opt->_sets * 4;
echo "Populated $real_count sets with {$obj_opt->_fields} in $tm sec\n";
