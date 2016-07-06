FROM wordpress:latest


RUN apt-get update #&& apt-get install -yqq unzip sed
RUN curl -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
RUN cd /tmp && chmod +x wp-cli.phar \
  && mv wp-cli.phar /usr/local/bin/wp


COPY plugins.tar.gz /usr/src/wordpress/wp-content/plugins.tar.gz
COPY feidernd /usr/src/wordpress/wp-content/themes/feidernd
RUN tar xzvf /usr/src/wordpress/wp-content/plugins.tar.gz
RUN rm /usr/src/wordpress/wp-content/plugins.tar.gz
#RUN chown -R www-data:www-data /usr/src/wordpress/wp-content/plugins/dataporten_oauth
RUN sed -i '$ d' /entrypoint.sh
RUN echo 'wp plugin install --activate dataporten-oauth --allow-root' >> /entrypoint.sh
RUN echo 'wp theme install twentyten --allow-root' >> /entrypoint.sh


RUN echo 'exec "$@"' >> /entrypoint.sh

VOLUME volume/ /var/www/

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
