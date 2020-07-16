#!/bin/bash

# Beanstalk
nohup beanstalkd 2>&1 &

# Start Redis for logging and unique ID generation and 
# make shutdown easy just by shutting down redis
redis-server --save "" --dir /tmp/

