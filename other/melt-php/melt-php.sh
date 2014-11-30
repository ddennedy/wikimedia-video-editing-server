#!/bin/sh
docker run -it --rm --link mysql:mysql -v "$(pwd)":/var/www ddennedy/melt-php "$@"
