## A demo beanstalk producer/consumer that uses PhpRedis

This is an attempt to replicate this [phpredis issue](https://github.com/phpredis/phpredis/issues/1543)

### Build the container

```bash
docker build .

# Or run ./start-container.sh if it's the newest container
docker run --privileged -it <container_id>
```

### Start the producer
```bash
docker exec -it <container_id> bash
# Starts the producer reading host, port, auth info from the file
# 'servers/aws-replica', sleeps between 1 and 900 seconds between
# messages, and each message has between 200-500 commands.
php producer.php --instance servers/aws-replica --sleep 1-900 --commands 200-500
```

### Start the consumer
```bash
# issue commands on the host info found in `servers/aws-replica` and use a pipeline
# for the command execution.  Other valid modes: 'atomic', 'multi', and 'multipipe'
php consumer.php --instance servers/aws-replica --mode pipe

```

### Instance file format
These files can contain host, port, and auth information used to connect to Redis.
They are simply text files in the following form:

```
host = myhost
port = myport
auth = mypass
```

Example:
```
host = localhost
port = 6379
```

Example:
```
host = localhost
port = 6379
auth = mysupersecretpassword
```
