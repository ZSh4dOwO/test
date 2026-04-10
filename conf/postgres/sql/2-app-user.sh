#!/bin/bash
set -e

# Grant permissions to the application user
# Uses psql -v variables to safely pass env vars into SQL
psql -v ON_ERROR_STOP=1 \
     -v php_user="$POSTGRES_PHP_USER" \
     -v db_name="$POSTGRES_DB" \
     --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'EOSQL'
    -- Grant SELECT on all public tables
    GRANT USAGE ON SCHEMA public TO :php_user;
    GRANT SELECT ON ALL TABLES IN SCHEMA public TO :php_user;
    
    -- Special permissions for app_user table (insert new users)
    GRANT INSERT, UPDATE ON public.app_user TO :php_user;
    GRANT USAGE, SELECT ON SEQUENCE app_user_id_seq TO :php_user;
    
    -- Allow the user to connect to the database
    GRANT CONNECT ON DATABASE :db_name TO :php_user;
EOSQL
