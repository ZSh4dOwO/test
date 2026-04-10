#!/bin/bash
set -e

#
# Création de l'utilisateur applicatif pour PostgreSQL
# Ce script s'exécute EN PREMIER lors de l'initialisation du conteneur
#

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<EOSQL
    -- Supprimer l'utilisateur s'il existe déjà (pour éviter les conflits)
    DROP USER IF EXISTS $POSTGRES_PHP_USER;

    -- Créer l'utilisateur avec le mot de passe spécifié dans docker-compose
    CREATE USER $POSTGRES_PHP_USER WITH PASSWORD '$POSTGRES_PHP_PASSWORD';

    -- Donner les permissions nécessaires
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO $POSTGRES_PHP_USER;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO $POSTGRES_PHP_USER;

    -- Les permissions spécifiques sur les tables seront accordées dans le script suivant
    -- après la création des tables
EOSQL
