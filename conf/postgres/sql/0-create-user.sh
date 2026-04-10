#!/bin/bash
set -e

#
# Create the application database user for PostgreSQL
# Uses environment variables from docker-compose instead of hardcoded credentials
# Uses psql -v variables to avoid SQL injection from special characters in passwords
#

psql -v ON_ERROR_STOP=1 \
     -v php_user="$POSTGRES_PHP_USER" \
     -v php_password="$POSTGRES_PHP_PASSWORD" \
     --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'EOSQL'
    -- Drop the user if it already exists (avoid conflicts)
    DROP USER IF EXISTS :php_user;

    -- Create the user with the password from environment variables
    CREATE USER :php_user WITH PASSWORD :'php_password';

    -- Grant necessary default privileges
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO :php_user;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO :php_user;
EOSQL
