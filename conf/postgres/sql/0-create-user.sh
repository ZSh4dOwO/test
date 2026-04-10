#!/bin/bash
set -e

# Create the application database user using environment variables
# instead of hardcoded credentials
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<EOSQL
    -- Supprimer l'utilisateur s'il existe déjà (pour éviter les conflits)
    DROP USER IF EXISTS ${POSTGRES_PHP_USER};

    -- Créer l'utilisateur avec le mot de passe spécifié dans docker-compose
    CREATE USER ${POSTGRES_PHP_USER} WITH PASSWORD '${POSTGRES_PHP_PASSWORD}';

    -- Donner les permissions nécessaires
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO ${POSTGRES_PHP_USER};
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO ${POSTGRES_PHP_USER};
EOSQL
