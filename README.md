phpmysql-backup
===============

A script to automatically create a local backup of a MySQL database in PHP.

It generates a SQL script that can be run directly in the database to restore it to the state save in the backup.

Requires a working PHP CLI installation along with a web interface (apache2, cgi, fcgi, fpm, etc.)

How it works
------------

Since some databases have a big number of records, the database is not actually back-ed up when web script is requested. Rather, when the script is executed from a non-CLI environment, it fires off a background process which actually does the backup, and returns immediately. This allows us to let the backups take as long as needed and not have to change the timeout configuration (since the timeout value is ignored in CLI).
