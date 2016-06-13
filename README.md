#dataporten-wordpress-plugin

This is a oAuth2.0 plugin for Wordpress with Dataporten. 

1. To run it with docker, run

```$ docker pull uninettno/dataporten-wordpress-plugin```

2. Create an env.list:

``` WORDPRESS_DB_HOST=**********
WORDPRESS_DB_USER=********
WORDPRESS_DB_PASSWORD=**********
WORDPRESS_DB_NAME=************

HOST=HOSTNAME/
TLS=false
DATAPORTEN_CLIENTID=******-****-****-****-**********
DATAPORTEN_CLIENTSECRET=******-****-***-******
DATAPORTEN_SCOPES=groups,userid,profile,userid-feide,email
DATAPORTEN_ROLESETS={"**USERGROUP**1": "editor", "**USERGROUP**2":"administrator"}
DATAPORTEN_DEFAULT_ROLE_ENABLED=1```

3. Start the image with:

```$ docker run --env-file=YOUR_ENV_FILE -p DESIRED_PORT:80 -t uninettno/dataporten-wordpress-plugin```


To install the plugin manually, clone the repository, and move the ```dataporten_oauth``` folder to your ```/plugins/``` folder in the Wordpress install directory. (Usually ```/var/www/html/wordpress/wp-content/plugins/```)