#!/bin/sh

# This assumes you have another container running mysql and beanstalkd.
# I run my mysql container with its own data (not using a docker volume) that
# does its own backup. For that, I am using TurnKey Linux with TKLBAM, which
# makes it easy:
# $ docker run --name mysql -itd turnkeylinux/mysql-13.0

# In that container, I have installed beanstalkd and running it with a
# persistence log that is also backed up:
# $ apt-get install beanstalkd
# $ vim /etc/default/beanstalkd
#   add "-b /var/lib/beanstalkd" to DAEMON_OPTS and uncomment START
# $ echo /var/lib/beanstalkd >> /etc/tklbam/overrides
# $ service beanstalkd start

sudo docker run -d --name php --link mysql:mysql -v "$(pwd)":/var/www/html php:5.5-apache

# Or, to run interactively:
#docker run -it --rm --name php --link mysql:mysql -v "$(pwd)":/var/www/html php:5.5-apache
