# TODO

- gérer les identifiants de connexion (nouveaux etc)
- modifier l'agencement des cartes pour avoir un site beau
- ajouter le détail des pathologies lorsqu'on appuie sur "voir détail"

# Projet TIDAL — Plateforme Acupuncture AAA

## Membres de l'équipe
- Lissandre LAFORETS
- Alexis CARON
- Léonard RIVAUX

---

## Démarrage du projet

### Prérequis
- Docker & Docker Compose installés
- Navigateur Firefox (ou recent)

### Construction initiale
```bash
# Si vous démarrez pour la première fois, ou après une erreur de connexion BD
docker-compose down -v  # Supprime les conteneurs et volumes persistants
docker-compose up --build
```

### Accès au site
- **Site principal** : http://localhost:50180/index.php
- **PgAdmin** (admin DB) : http://localhost:50181

### Identifiants de connexion
**Utilisateur application :**
- Email : `admin@aaa.local`
- Mot de passe : `Acupuncture123!`

**PostgreSQL (admin BD) :**
- Utilisateur : `postgres`
- Mot de passe : `postgres`

---

## Architecture technique

### Stack
- **Backend** : PHP 8+ (aucun framework)
- **Template** : Twig (uniquement moteur autorisé)
- **BD** : PostgreSQL 16
- **Frontend** : HTML5, CSS3, JavaScript vanilla (progressive enhancement)

### Arborescence
```
src/
├── index.php          # Routeur principal + logique métier
├── Twig/
│   ├── base.html.twig        # Layout principal
│   ├── index.html.twig       # Accueil (avec recherche)
│   ├── pathologies.html.twig # Liste pathologies
│   └── patho.html.twig       # Détail pathologie
├── script.js          # Modal UI + progressive enhancements
└── style.css          # Design tokens + composants

conf/
├── php/               # Config Apache/PHP
├── postgres/          # Config Dockerfile PostgreSQL
│   └── sql/
│       ├── 1-acudb-tables.sql    # Tables acupuncture + app_user
│       └── 2-app-user.sh         # Init utilisateur PostgreSQL
└── pgadmin/          # Config PgAdmin (accès BD admin)
```

### Base de données
- Tables originales acupuncture : **respectées** (non modifiées)
- Nouvelles tables ajoutées : `app_user` (utilisateurs application)
- Liens : `keywords` ↔ `symptome` ↔ `patho` ↔ `meridien`

---

## Résolution de problèmes

### Erreur : "password authentication failed for user 'acu'"
**Cause** : Le conteneur PostgreSQL n'a pas pu initialiser l'utilisateur `acu`

**Solution** :
```bash
# Supprimer tout (conteneurs + volumes persistants)
docker-compose down -v

# Relancer avec reconstruction
docker-compose up --build
```

### Erreur : "Connection refused"
**Vérifier** : Les services sont en cours d'exécution
```bash
docker-compose ps
```
Attendre 10-15 secondes que PostgreSQL démarre complètement.

---

## Ce qui a été accompli

### Phase 1 - Architecture
- Routeur centralisé (`index.php`)
- Templates Twig pour toutes les pages
- Connexion sécurisée à PostgreSQL
- Gestion de session

### Phase 2 - Fonctionnalités core
- Affichage liste pathologies
- Filtres type + méridien (serveur-side)
- Page détail pathologie + symptômes
- Recherche mot-clé depuis l'accueil

### Phase 3 - Accessibilité & UX
- Skip-link + aria labels
- Modal de connexion accessible (Escape)
- Navigation clavier complète
- Messages flash utilisateur

---

## Prochaines étapes (optionnel)

- [ ] Favoris / marquer pathologies
- [ ] Historique recherche utilisateur
- [ ] Export résultats (PDF/CSV)
- [ ] API JSON pour mobile
