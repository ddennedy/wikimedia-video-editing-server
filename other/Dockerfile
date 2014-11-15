FROM debian:7
MAINTAINER Dan Dennedy <dan@dennedy.org>

RUN apt-get update && apt-get -y install php5 php5-mysqlnd && apt-get clean

EXPOSE 80
EXPOSE 443

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
