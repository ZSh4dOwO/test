--
-- Création de l'utilisateur applicatif pour PostgreSQL
-- Ce script s'exécute EN PREMIER lors de l'initialisation du conteneur
--

-- Supprimer l'utilisateur s'il existe déjà (pour éviter les conflits)
DROP USER IF EXISTS acu;

-- Créer l'utilisateur avec le mot de passe spécifié dans docker-compose
CREATE USER acu WITH PASSWORD 'acu';

-- Donner les permissions nécessaires
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO acu;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO acu;

-- Les permissions spécifiques sur les tables seront accordées dans le script suivant
-- après la création des tables
