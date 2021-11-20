#!/usr/bin/env sh

sqlite3 db.sqlite3 ".read sql/CreateTables.sql"
sqlite3 db.sqlite3 ".read sql/InsertExamples.sql"
sudo chown www-data:www-data db.sqlite3
