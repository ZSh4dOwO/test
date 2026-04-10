#!/bin/bash
set -e

# Donner les permissions sur les tables à l'utilisateur acu
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<EOSQL
    -- Accorder les permissions SELECT sur toutes les tables publiques à l'utilisateur acu
    GRANT USAGE ON SCHEMA public TO acu;
    GRANT SELECT ON ALL TABLES IN SCHEMA public TO acu;
    
    -- Permissions spéciales pour la table app_user (insertion de nouveaux utilisateurs)
    GRANT INSERT, UPDATE ON public.app_user TO acu;
    GRANT USAGE, SELECT ON SEQUENCE app_user_id_seq TO acu;
    
    -- Assurer que l'utilisateur acu peut se connecter à la BDD
    GRANT CONNECT ON DATABASE $POSTGRES_DB TO acu;
EOSQL
