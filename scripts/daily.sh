#!/bin/bash

MYSQL_PATH="mysql"
MYSQL_HOST="localhost"
MYSQL_DATABASE="vldashboard"
MYSQL_USER="root"
MYSQL_PASSWORD="zaq12345"
MYSQL_PORT=3306

$MYSQL_PATH --user=$MYSQL_USER --password=$MYSQL_PASSWORD --database=$MYSQL_DATABASE --execute="DROP TABLE if exists dash_vl_request_form_current; CREATE TABLE dash_vl_request_form_current LIKE dash_vl_request_form; INSERT dash_vl_request_form_current SELECT * FROM dash_vl_request_form where sample_collection_date >= DATE_SUB(now(), INTERVAL 12 MONTH);"
