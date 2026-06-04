# 🍃 NutriPetit

> **Application PWA d'analyse nutritionnelle adaptée aux nourrissons (0-3 ans)**, basée sur les recommandations officielles ANSES, OMS, EFSA et PNNS.

[![CI](https://github.com/DamienCH33/nutripetit/actions/workflows/ci.yml/badge.svg)](https://github.com/DamienCH33/nutripetit/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.4_LTS-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Redis](https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white)](https://redis.io/)

---

## 📌 En quelques mots

Yuka et les apps de scan généralistes appliquent les mêmes barèmes nutritionnels aux adultes et aux nourrissons. **C'est un non-sens nutritionnel.** Les besoins d'un bébé de 6 mois ne sont pas ceux d'un adulte.

**NutriPetit corrige cette limite** : scan d'un produit alimentaire → score nutritionnel spécifique nourrisson → recommandations sourcées sur les organismes officiels.

### 🎯 Différenciateurs

- **Scoring 100% déterministe** : zéro IA, chaque règle est sourcée et auditable
- **Adapté à l'âge** : les barèmes diffèrent selon que l'enfant a 6, 12 ou 24 mois
- **Transparence par construction** : chaque malus/bonus est tracé avec sa source officielle
- **PWA installable** : aucun store, aucun compte, scan immédiat
- **Conformité RGPD maximale** : aucune donnée personnelle de l'enfant ne transite sur les serveurs

---

## 🛠️ Stack technique

### Backend

- **PHP 8.4** + **Symfony 7.4 LTS** (Application skeleton)
- **PostgreSQL 16** (base de données métier)
- **Redis 7** (cache haute performance, 2 pools dédiés)
- **Doctrine ORM** (avec migrations versionnées)
- **Symfony UX** (Stimulus + Live Components + Icons)

### Frontend

- **Twig** + Symfony UX (Turbo + Stimulus)
- **AssetMapper** (pas de Webpack/Vite)
- **Symfony UX Icons** + Iconify (Lucide icons)
- **CSS modulaire** avec BEM et tokens `--np-*`

### Scan & API

- **@zxing/browser** (lecture code-barres)
- **OpenFoodFacts API** (référentiel produits open data)

### Qualité de code

- **PHP CS Fixer** (preset Symfony + PHP 8.4 migration)
- **PHPStan** niveau 6 (montée à 8 prévue)
- **GitHub Actions CI** (qualité + tests à chaque push)
- **Conventional Commits**

### Hébergement

- **Railway** + **FrankenPHP** (containerisation moderne)

---

## 🏗️ Architecture

### Vue d'ensemble

```
┌─────────────────────────────────────────────────┐
│                Couche présentation              │
│  Controllers │ Twig │ Stimulus │ Symfony UX     │
└────────────────────────┬────────────────────────┘
                         │
┌────────────────────────▼────────────────────────┐
│                  Couche métier                  │
│        Services │ DTO │ Rule Evaluators         │
└────────────────────────┬────────────────────────┘
                         │
┌────────────────────────▼────────────────────────┐
│                Couche persistance               │
│           Entities │ Repositories               │
└──┬──────────────────────────────────────────┬───┘
   │                                          │
   ▼                                          ▼
┌─────────────┐                       ┌─────────────┐
│ PostgreSQL  │                       │   Redis     │
│ (durable)   │                       │  (cache)    │
└─────────────┘                       └─────────────┘
```

### Architecture dual-layout

NutriPetit sépare la **landing publique SEO-friendly** de l'**interface PWA mobile** :

| URL      | Layout  | Contexte                                     |
| -------- | ------- | -------------------------------------------- |
| `/`      | Landing | Site vitrine pour les nouveaux visiteurs     |
| `/app/*` | PWA     | Application installable, container 480px max |

Cette séparation permet :

- Une **landing optimisée pour le SEO et la conversion**
- Une **PWA optimisée pour l'usage mobile rapide en magasin**

---

## 🧮 Le moteur de scoring

### Principe

Le scoring NutriPetit suit le **pattern Strategy** : chaque règle de scoring est une classe isolée, testable, sourcée.

```
ScoreCalculator (chef d'orchestre)
        │
        ├── Charge les ScoringRule actives pour algoVersion
        ├── Filtre par âge du bébé
        └── Délègue l'évaluation à chaque RuleEvaluator
                │
                ├── AddedSugarsEvaluator (OMS 2015)
                ├── AddedSaltEvaluator (ANSES)
                ├── SweetenersEvaluator (EFSA + ANSES)
                ├── ArtificialFlavorsEvaluator (PNNS)
                ├── PalmOilEvaluator (PNNS + ANSES)
                ├── ExcessiveProteinEvaluator (OMS)
                ├── MajorAllergensEvaluator (INCO 1169/2011)
                └── OrganicCertifiedEvaluator (Agence Bio)
```

### Versionning de l'algorithme

Chaque score calculé conserve sa `algoVersion` (ex: `'1.0.0'`). Une modification de règle entraîne une nouvelle version (`'1.1.0'`), sans casser les scores passés.

**Reproductibilité garantie** : un score calculé en 2026 reste recalculable à l'identique en 2030.

### Échelle de score

| Score  | Niveau    | Interprétation           |
| ------ | --------- | ------------------------ |
| 80-100 | Excellent | Adapté à votre bébé      |
| 60-79  | Bon       | Acceptable, sans plus    |
| 40-59  | Moyen     | À limiter ou occasionnel |
| 0-39   | À éviter  | Non recommandé pour bébé |

---

## 💾 Modèle de données

### Entités principales

| Entité        | Rôle                                   | Particularité                       |
| ------------- | -------------------------------------- | ----------------------------------- |
| `Product`     | Cache local des produits OpenFoodFacts | Clé primaire = EAN-13               |
| `ScoringRule` | Règle de scoring versionnée et sourcée | Soft delete via `isActive`          |
| `ScoreResult` | Résultat d'un calcul de score          | ULID + détail des règles appliquées |

### Choix d'architecture notables

- **EAN comme clé primaire** : identifiant universel mondial, évite les doublons
- **JSONB pour les données souples** : nutriments, allergens, additives, appliedRules
- **ULID** : unicité + ordonnabilité chronologique (mieux que UUID pour les listes triées)
- **Versionning algorithme** : chaque `ScoreResult` garde sa `algoVersion` pour reproductibilité

---

## ⚡ Cache Redis : 2 pools dédiés

| Pool                 | TTL      | Usage                                                  |
| -------------------- | -------- | ------------------------------------------------------ |
| `nutripetit.off_api` | 7 jours  | Réponses OpenFoodFacts (les produits changent peu)     |
| `nutripetit.scores`  | 30 jours | Scores calculés (déterministes pour une `algoVersion`) |

**Pourquoi 2 pools ?** Les scores sont **plus stables** que les produits. Un seul pool nous obligerait au TTL le plus court (7 jours), gaspillant des calculs identiques.

---

## ⚖️ Conformité juridique et éthique

NutriPetit fournit une **analyse nutritionnelle informative** basée sur des **recommandations officielles**.

**Ce score ne constitue pas un avis médical** et ne remplace pas l'avis d'un professionnel de santé.

### Garanties

1. **Aucune affirmation médicale** : NutriPetit constate la composition et cite les autorités
2. **Sources officielles vérifiables** : chaque règle a son URL source publique
3. **Traçabilité totale** : chaque score peut être expliqué règle par règle
4. **Conformité RGPD** : profil bébé en localStorage, aucune donnée d'enfant sur les serveurs

### Sources officielles

- **ANSES** — Agence nationale de sécurité sanitaire de l'alimentation
- **OMS** — Organisation Mondiale de la Santé
- **EFSA** — Autorité européenne de sécurité des aliments
- **PNNS** — Programme National Nutrition Santé

---

## 🚀 Installation locale

### Prérequis

- Docker + Docker Compose
- PHP 8.4
- Composer 2.x
- Symfony CLI (optionnel mais recommandé)

### Démarrage

```bash
# Cloner
git clone https://github.com/DamienCH33/nutripetit.git
cd nutripetit

# Installer les dépendances
composer install

# Démarrer les services (Postgres + Redis + Mailpit)
docker compose up -d

# Créer la base de données
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction

# Charger les règles de scoring (fixtures)
php bin/console doctrine:fixtures:load --no-interaction

# Démarrer le serveur Symfony
symfony serve -d
```

L'application est accessible sur **https://localhost:8000**.

### Variables d'environnement

Copier `.env` en `.env.local` et adapter si besoin :

```env
DATABASE_URL="postgresql://nutripetit:nutripetit@127.0.0.1:5433/nutripetit?serverVersion=16&charset=utf8"
REDIS_URL=redis://127.0.0.1:6380
```

---

## 🧪 Qualité de code

```bash
# Vérifier le formatage
composer cs:check

# Corriger le formatage
composer cs:fix

# Analyse statique
composer stan

# Tests unitaires
composer test

# Tout en une commande
composer qa
```

Le workflow CI GitHub Actions exécute automatiquement ces vérifications à chaque push.

---

## 🗺️ Roadmap

### V1 (Sprint actuel — 3 semaines)

- [x] Architecture dual-layout (landing + PWA)
- [x] Design system + composants Twig + Stimulus
- [x] Configuration Redis avec pools dédiés
- [x] Entités Doctrine + migrations
- [ ] DTO + ScoreCalculator + 8 RuleEvaluators
- [ ] OpenFoodFactsClient avec cache
- [ ] Scanner code-barres (ZXing)
- [ ] Validator EAN-13
- [ ] PWA manifest + service worker
- [ ] Tests unitaires (>70% couverture sur le ScoreCalculator)
- [ ] Déploiement Railway

### V2 (post-portfolio)

- [ ] Authentification + entité User
- [ ] Migration localStorage → entité `BabyProfile` persistée
- [ ] Synchronisation cross-device
- [ ] Recherche par marque/catégorie
- [ ] Notifications recommandations alternatives
- [ ] Mode hors-ligne complet (Service Worker avancé)
- [ ] Statistiques personnelles d'évolution

---

## 📂 Structure du projet

```
nutripetit/
├── .github/workflows/         # CI GitHub Actions
├── assets/                    # Frontend (CSS + Stimulus + JS)
│   ├── controllers/           # Stimulus controllers
│   └── styles/                # CSS modulaire (BEM)
│       ├── base/              # Variables, reset, typography
│       ├── components/        # Buttons, cards, navigation
│       └── pages/             # Styles spécifiques pages
├── config/                    # Configuration Symfony
│   └── packages/              # Cache, doctrine, ux_icons, etc.
├── migrations/                # Migrations Doctrine versionnées
├── public/                    # Document root web
├── src/
│   ├── Controller/            # Controllers Symfony (un par domaine)
│   ├── Dto/                   # Data Transfer Objects
│   ├── Entity/                # Entités Doctrine
│   ├── Repository/            # Repositories Doctrine
│   └── Service/               # Services métier
│       └── Scoring/           # Moteur de scoring
│           └── Evaluator/     # Les 8 évaluateurs de règles
├── templates/                 # Twig
│   ├── base/                  # Layouts (landing + pwa)
│   ├── _partials/             # Headers, footers, nav
│   └── pages/                 # Pages métier
└── tests/                     # Tests PHPUnit
```

---

## 👤 Auteur

**Damien Chauveau** — Développeur PHP/Symfony basé à Bordeaux

- 🌐 [Portfolio](https://damienchauveau-dev.fr)
- 💼 [LinkedIn](https://www.linkedin.com/in/damienchauveau)
- 💻 [GitHub](https://github.com/DamienCH33)

---

## 📜 Licence

Code source disponible sous licence MIT.

Données produits issues d'**Open Food Facts** (licence ODbL).

---

> **NutriPetit fournit une analyse nutritionnelle informative basée sur des recommandations officielles. Ce score ne constitue pas un avis médical et ne remplace pas l'avis d'un professionnel de santé.**
