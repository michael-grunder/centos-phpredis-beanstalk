#!/bin/bash

if [[ -z "$1" ]]; then
    ID=$(docker ps|head -n 2|tail -n 1|awk '{print $1}')
else
    ID=$1
fi

echo "Connecting to container: $ID"
docker exec -it "$ID" bash
