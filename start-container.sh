#!/bin/bash

if [[ -z "$1" ]]; then
    ID=$(docker images|head -n 2|tail -n 1|awk '{print $3}')
else
    ID=$1
fi

echo "Starting container: $ID"
docker run --privileged -it "$ID"
