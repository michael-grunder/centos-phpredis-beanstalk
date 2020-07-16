#!/bin/bash

printUsage() {
    echo "Usage: $0 <log-dir> <manifest file> [consumer args]"
    exit 1
}

verboseRun() {
    echo "Running: $@"
    $@
}

[[ ! -z "$2" ]] || printUsage

DIR=${1%/}
MANIFEST=$2

if [[ ! -d "$DIR" ]]; then
    echo "Error:  '$DIR' is not a path"
    exit -1
fi

if [[ ! -f "$MANIFEST" ]]; then
    echo "Error: '$MANIFEST' is not a file"
    exit -1
fi

shift 2

while read -r CHANNEL;
do
    LOG=$DIR/consumer-$CHANNEL.log
    echo "Running: php consumer.php --channel $CHANNEL $@ > $LOG 2>&1 &"
    nohup php consumer.php --channel "$CHANNEL" $@ > $LOG 2>&1 &
done <<< $(cat $MANIFEST)
