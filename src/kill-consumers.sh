#!/bin/bash

CHAN=$1

if [[ ! -z "$CHAN" ]]; then
    ps aux|grep [c]onsumer.php|awk '{print $2}'|xargs kill
else
    ps aux|grep [c]onsumer.php|grep "$CHAN"| awk '{print $2}'|xargs kill
fi
