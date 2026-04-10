#!/bin/bash
set -e

# Script d'attente pour PostgreSQL
# Ce script s'exécute quand le conteneur PHP démarre, et attend
# que PostgreSQL soit accessible avant de démarrer Apache

DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}
DB_USER=${DB_USER:-acu}
DB_DB=${DB_DB:-acudb}

echo "Attente de la disponibilité de PostgreSQL sur $DB_HOST:$DB_PORT..."

# Boucle d'attente jusqu'à 30 secondes
for i in {1..30}; do
    if pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_DB" > /dev/null 2>&1; then
        echo "✓ PostgreSQL est maintain!"
        break
    fi
    
    if [ $i -eq 30 ]; then
        echo "✗ Timeout : PostgreSQL n'a pas répondu après 30 secondes"
        exit 1
    fi
    
    echo "  Tentative $i/30 - Attente de PostgreSQL..."
    sleep 1
done

# Démarrer Apache en tant que service
exec apache2-foreground
