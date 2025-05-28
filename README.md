Containerizing PHP,Apache and MySQL
===================================

### Introduction
The project structure is as follows :

```
.
├── apache
│   ├── apache_php.conf
│   └── Dockerfile
├── docker-compose.yml
├── Jenkinsfile
├── k8s
│   ├── apache-prod.yaml
│   ├── mysql-prod.yaml
│   └── php-prod.yaml
├── mysql
│   ├── Dockerfile
│   └── dump.sql
├── php
│   └── Dockerfile
├── public
│   ├── config.php
│   ├── create.php
│   ├── delete.php
│   ├── employee.php
│   ├── error.php
│   ├── healthcheck.php
│   ├── index.php
│   ├── logout.php
│   ├── read.php
│   └── update.php
├── README.md
└── sonar-project.properties

```

You can run "docker-compose up" from the root of the project to run this project, and point your browser to http://localhost:8080 to see the project running.

### Docker Compose

Docker compose allows us to define the dependencies for the services, networks, volumes, etc as code.


### Networking

We're going to have Apache proxy connections which require PHP rendering to port 9000 of our PHP container, and then have the PHP container serve those out as rendered HTML.

We need an apache vhost configuration file that is set up to proxy these requests for PHP files to the PHP container. So in the Dockerfile for Apache we have defined above, we add this file and then include it in the base httpd.conf file. This is for the proxying which allows us to decouple Apache and PHP. Here we called it apache_php.conf and we have the proxying modules defined as well as the VirtualHost.

### Volumes

Both the PHP and Apache containers have access to a "volume" that we define in the docker-compose.yml file which maps the public folder of our repository to the respective services for them to access. When we do this, we map a folder on the host filesystem (outside of the container context) to inside of the running containers.This allows us to edit the file outside of the container, yet have the container serve the updated PHP code out as soon as changes are saved.

And we also use "db_data" volume to store the contents of database, so that even if containers are terminated the data won't get deleted.

### PHP CRUD application

We'll use the following PHP application to demonstrate everything:

"dump.sql" creates a table 'employees' and 'login' in the mydb database. 

"config.php" connects the php code with the 'employees' table of mydb database.

"index.php" login page for employee website.

"index.php" logout page for employee website.

"employee.php" displays records from "employees" table.

"create.php" generates web form that is used to insert records in the employees table.

"read.php" retrieves the records from the employees table.

"update.php" updates records in employees table.

"delete.php" deletes records in employees table.

"error.php" will be displyed if a request is invalid.

