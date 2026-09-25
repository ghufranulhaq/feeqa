#!/bin/sh
# Runs once when the postgres container's data volume is first initialised
# (official image convention: /docker-entrypoint-initdb.d/*). Creates a
# second database for the test suite, name containing "test" (plan D20), so
# tests never touch the development database.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${POSTGRES_DB}_test" OWNER "$POSTGRES_USER";
EOSQL
