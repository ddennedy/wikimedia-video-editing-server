#!/bin/sh
sudo docker run -it --rm --link mysql:mysql -v "$(pwd)":/var/www/html php:5.5-apache \
    php /var/www/html/index.php test
