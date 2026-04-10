#!/bin/bash
set -e

#
# Create the application database user for PostgreSQL
# Uses environment variables from docker-compose instead of hardcoded credentials
#

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<EOSQL
    -- Drop the user if it already exists (avoid conflicts)
    DROP USER IF EXISTS ${POSTGRES_PHP_USER};

    -- Create the user with the password from environment variables
    CREATE USER ${POSTGRES_PHP_USER} WITH PASSWORD '${POSTGRES_PHP_PASSWORD}';

    -- Grant necessary default privileges
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO ${POSTGRES_PHP_USER};
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO ${POSTGRES_PHP_USER};
EOSQL
