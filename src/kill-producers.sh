#!/bin/bash

CHAN=$1

if [[ ! -z "$CHAN" ]]; then
    ps aux|grep [p]roducer.php|awk '{print $2}'|xargs kill
else
    ps aux|grep [p]roducer.php|grep "$CHAN"| awk '{print $2}'|xargs kill
fi
