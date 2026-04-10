#!/bin/bash
set -e

# Donner les permissions sur les tables à l'utilisateur applicatif
# Utilise la variable d'environnement POSTGRES_PHP_USER définie dans docker-compose.yaml
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<EOSQL
    -- Accorder les permissions SELECT sur toutes les tables publiques
    GRANT USAGE ON SCHEMA public TO $POSTGRES_PHP_USER;
    GRANT SELECT ON ALL TABLES IN SCHEMA public TO $POSTGRES_PHP_USER;
    
    -- Permissions spéciales pour la table app_user (insertion de nouveaux utilisateurs)
    GRANT INSERT, UPDATE ON public.app_user TO $POSTGRES_PHP_USER;
    GRANT USAGE, SELECT ON SEQUENCE app_user_id_seq TO $POSTGRES_PHP_USER;
    
    -- Assurer que l'utilisateur peut se connecter à la BDD
    GRANT CONNECT ON DATABASE $POSTGRES_DB TO $POSTGRES_PHP_USER;
EOSQL
