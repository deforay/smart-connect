#!/bin/bash

mysql_path="/Applications/MAMP/Library/bin/mysql"
mysql_host="localhost"
mysql_dbname="vldashboard"
mysql_username="root"
mysql_password="zaq12345"
mysql_port="3306"

enable_vl=true
enable_eid=false
enable_covid19=false


if [ "$enable_vl" = true ] ; then
$mysql_path --user=$mysql_username --password=$mysql_password --database=$mysql_dbname --execute="DROP TABLE if exists dash_form_vl_current; CREATE TABLE dash_form_vl_current LIKE dash_form_vl; INSERT dash_form_vl_current SELECT * FROM dash_form_vl where sample_collection_date >= DATE_SUB(now(), INTERVAL 12 MONTH);"
fi 

if [ "$enable_eid" = true ]  ; then
$mysql_path --user=$mysql_username --password=$mysql_password --database=$mysql_dbname --execute="DROP TABLE if exists dash_form_eid_current; CREATE TABLE dash_form_eid_current LIKE dash_form_eid; INSERT dash_form_eid_current SELECT * FROM dash_form_eid where sample_collection_date >= DATE_SUB(now(), INTERVAL 12 MONTH);"
fi 

if [ "$enable_covid19" = true ]  ; then
$mysql_path --user=$mysql_username --password=$mysql_password --database=$mysql_dbname --execute="DROP TABLE if exists dash_form_covid19_current; CREATE TABLE dash_form_covid19_current LIKE dash_form_covid19; INSERT dash_form_covid19_current SELECT * FROM dash_form_covid19 where sample_collection_date >= DATE_SUB(now(), INTERVAL 12 MONTH);"
fi 



