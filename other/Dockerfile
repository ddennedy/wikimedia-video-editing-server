FROM debian:7
MAINTAINER Dan Dennedy <dan@dennedy.org>

RUN apt-get update && apt-get -y install php5 php5-mysqlnd php5-curl && apt-get clean
RUN printf "upload_max_filesize=0\npost_max_size=0\n" > /etc/php5/apache2/conf.d/99-my.ini

EXPOSE 80
EXPOSE 443

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
