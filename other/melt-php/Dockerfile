FROM ddennedy/melt
MAINTAINER Dan Dennedy <dan@dennedy.org>

RUN apt-get update
# LADSPA plugins
RUN apt-get install -y amb-plugins blepvco blop cmt fil-plugins invada-studio-plugins-ladspa ladspa-foo-plugins mcp-plugins omins rev-plugins swh-plugins tap-plugins vco-plugins wah-plugins zam-plugins
# Fonts
RUN apt-get install -y $(apt-cache --quiet --names-only search '^ttf-.+' | awk '{print $1}' | xargs)
# Image format plugins
RUN apt-get install -y librsvg2-common
# PHP and related
RUN apt-get -y install beanstalkd php5 php5-mysqlnd php5-curl
RUN apt-get clean -y
