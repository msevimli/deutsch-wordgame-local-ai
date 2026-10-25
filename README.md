# Docker Lamp

This is a docker image and container combination for up and running LAMP Server
Useful for Shopware 6, Wordpress/WooCommerce or similar projects



## Features

- Ubuntu latest pull from hub
- Apache2 with php-8.3
- Mysql 
- Phpmyadmin

  
## Configuration 

to build dockerfile for image and container

```bash 
  docker build -t lamp-stack .
  docker-compose up -d --build
  or
  docker run -p 8080:80 -p 3307:3306 -e MYSQL_ROOT_PASSWORD=root -it lamp-stack

```
## Phpmyadmin 
To access phpmyadmin edit your host file in host machine 
```bash 
  127.0.0.1    docker.phpmyadmin.dev

```

All Done!

Base .src folder at host machine linked to /www/html/ in server

Enjoy..
