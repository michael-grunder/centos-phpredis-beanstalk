#!/bin/bash

printUsage() {
    echo "Usage: $0 <num-jobs> <channel-prefix> <log-dir> [producer args]"
    exit 1
}

verboseRun() {
    echo "Running: $@"
    $@
}

[[ ! -z "$3" ]] || printUsage

JOBS=$1
PREFIX=$2
DIR=${3%/}
MANIFEST=$(mktemp -p /tmp XXXXX)

if [[ "$JOBS" < "1" ]]; then
    echo "Error:  Job count must be > 0"
    exit 1
fi

if [[ ! -d "$DIR" ]]; then
    echo "Error:  '$DIR' is not a path"
    exit -1
fi

shift 3

for N in $(seq 0 $JOBS); do
    CHAN="${PREFIX}$N"
    LOG=$DIR/producer-$CHAN.log

    echo "php producer.php --channel $CHAN $@ > $LOG 2>&1 &"
    nohup php producer.php --channel $CHAN $@ > "$LOG" 2>&1 &
    echo "$CHAN" >> "$MANIFEST"
done

echo ""
echo "To start corresponding consumers:"
echo ""
echo "./spin-consumers.sh $DIR $MANIFEST $@"
