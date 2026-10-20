# Audits — NutriPetit

Passe du **15/10/2026** (complétée le 19/10), sur l'API Symfony (`nutripetit`) et la PWA Angular (`nutripetit-front`), même méthode que l'audit Cap Monta du 25/09. Chaque ligne dit ce qui a été **constaté** et comment : test automatisé, build, lecture du code, ou mesure.

Légende : **OK** constaté bon · **Corrigé** faux, corrigé (branche `audit/2026-10-15`) et couvert par un test quand c'est testable · **À faire** identifié, reste ouvert · **Au déploiement** ne se vérifie qu'en ligne (`bin/audit-prod.sh`) · **À trancher** décision produit.

## Bilan

| | |
|---|---|
| Bloquants | 2, corrigés |
| Importants | 11, dont 10 corrigés |
| Tests API | 208 PHPUnit verts (200 avant la passe) · php-cs-fixer propre · PHPStan : 1 erreur, **déjà présente sur `main`** |
| Tests front | 25 Vitest verts. Avant la passe, la suite **ne compilait pas** (specs générées jamais mises à jour) |
| Build front | OK. Bundle initial **193 kB → 80 kB** transférés |
| Non couvert | prod en ligne (accès bloqué depuis l'environnement d'audit : script fourni), charge, mobile 5 largeurs, accessibilité complète |

Les deux bloquants :

1. **L'alerte « miel avant 1 an » n'existait pas en prod.** `HoneyBotulismEvaluator` était codé et testé, mais la règle `honey_botulism` n'était pas dans `ScoringRulesProvider`. Comme `app:sync-scoring-rules` réécrit la table des règles à chaque déploiement, l'evaluator ne s'exécutait jamais. Un produit bébé au miel n'affichait aucune alerte. Même cas pour `total_sugars_energy` (sucres > 15 % de l'énergie, OMS Europe).
2. **L'âge du bébé n'arrivait jamais à l'API.** Le front Angular appelait `/api/scan/{ean}` sans `?age=`. Toutes les règles limitées à une tranche d'âge étaient donc ignorées, notamment **le sel ajouté avant 1 an** et les badges fer et oméga-3 dès 6 mois.

---

# 1 — Scoring

| Point | Comment | État |
|---|---|---|
| Règles semées ↔ evaluators | Comparaison des codes | **Corrigé** — `honey_botulism` (−35, source ANSES) et `total_sugars_energy` (−15, seuil 15 %, 6-36 mois) ajoutées. Algo passé en **1.3.0** |
| Garde-fou contre un nouvel oubli | Test | **Corrigé** — `testEveryEvaluatorHasASeededRule` échoue si un evaluator n'a pas de règle |
| Version de l'algo à deux endroits | `ScoreCalculator` / `ScoringRulesProvider` | **Corrigé** — une seule source de vérité |
| Âge transmis au scan | Lecture + test front | **Corrigé** — `ScanService.scan(ean, âge)` |
| Âge inconnu : seules les règles 0-36 mois s'appliquent | Lecture | **À trancher** — le sel (0-12 mois) ne compte pas quand l'âge n'est pas renseigné. Option prudente : appliquer les règles dès 0 mois |
| Fiche OFF sans ingrédients : score calculé quand même | Lecture + test | **Corrigé** — le champ `dataIncomplete` était **toujours faux**. Il signale maintenant un score partiel, et la fiche affiche un bandeau |
| Plafonner le score d'un produit sans ingrédients | — | **À trancher** |
| Additif E921 (L-cystéine) « non documenté » | Scan 3701293900233 | **À faire** — à ajouter à la table des additifs |

# 2 — Détection des produits bébé

| Point | Comment | État |
|---|---|---|
| Marques en plusieurs mots (« Good Goût »…) | Test | **Corrigé** — OFF envoie des slugs (`good-gout`) que la comparaison ne reconnaissait pas |
| Gammes bébé de distributeurs | Test | **Corrigé** — « Mots d'enfants » (Leclerc) et « U Tout Petits » ajoutées |
| Mentions d'âge hors liste (« dès 15 mois ») | Test | **Corrigé** — expression générale. « Comté 18 mois » reste refusé (testé) |
| Produit refusé : la copie locale restait 90 jours | Lecture | **Corrigé** — un produit refusé est relu sur OFF au plus une fois par jour, et le cache OFF passe de 7 jours à 24 h |
| Lien pour compléter la fiche OFF depuis l'erreur | Test front | **Corrigé** |
| 3564706788480 (Leclerc, assiette dès 15 mois) | Fiche OFF | **À faire hors code** — sur OFF, la fiche n'a ni catégorie, ni ingrédients, et la marque est « Marque Repère ». Une fois la fiche complétée, NutriPetit la reconnaîtra |

# 3 — Sécurité

| Test | Résultat | État |
|---|---|---|
| Cookie `np_session` en prod | Sans `Secure` : derrière Caddy, Symfony voit du http | **Corrigé** — `Secure` en prod, `SameSite=Lax` (front et API sont sur le même domaine) |
| `isSecure()` et IP réelle derrière Caddy | robots.txt en prod affiche `Sitemap: http://…` | **Corrigé** (branche) — Caddy transmet `X-Forwarded-Proto: https` et l'IP du visiteur. **Au déploiement** : vérifier que le limiteur de scans n'est pas un compteur global |
| En-têtes de sécurité sur `/app` | Caddyfile du front | **Corrigé** (branche) — CSP, HSTS, nosniff, X-Frame-Options, Permissions-Policy (caméra autorisée seulement sur le site lui-même) |
| CSRF sur l'effacement de l'historique | Test | **OK** |
| Accès à l'historique d'un autre cookie | Jeton de 256 bits | **OK** |
| php.ini de prod | Absent de l'image (valeurs par défaut : `display_errors=1`, `X-Powered-By`) | **Corrigé** — `php.ini-production` + `expose_php=Off` |
| `.dockerignore` | Absent | **Corrigé** |
| Dépendances vulnérables | `composer audit` impossible ici (Packagist bloqué) | **Au déploiement** — à lancer chez toi. La CI le fait déjà |
| PHPStan rouge sur `main` | `ScoringRule::$id` | **À faire** — la CI « Quality » échoue donc déjà |

# 4 — Front / PWA

| Point | État |
|---|---|
| Suite de tests qui ne compilait pas | **Corrigé** — 25 tests, dont l'âge envoyé, le CSRF, le bandeau données partielles et la caméra |
| Caméra restée allumée si on quitte le scanner pendant la demande d'autorisation | **Corrigé** — le test échoue sur l'ancien code |
| Chargement à la demande des pages | **Corrigé** — le scanner (ZXing, 90 kB) n'est plus dans le bundle initial |
| Google Fonts chargé depuis Google | **Corrigé** — Poppins auto-hébergée (comme la vitrine) |
| `lang="en"` sur une appli en français | **Corrigé** |
| Route inconnue → page vide | **Corrigé** — retour à l'accueil |
| Installation PWA impossible depuis `/app` | **Corrigé et déployé le 19/10** (`fix/pwa-install`) |
| Fichier JS manquant servi comme index.html → page blanche | **Corrigé et déployé le 19/10** (`fix/page-blanche`) |
| Fichier vide `true]` versionné | **Corrigé** |

# 5 — SEO

| Point | État |
|---|---|
| URL absolues en `http://` (sitemap, robots, og:image) | **Corrigé** via Caddy (voir 3) |
| robots.txt : `/admin` et `/api/` | **Corrigé** — interdits |

# 6 — RGPD

| Point | État |
|---|---|
| « Dernière mise à jour » = date du jour, à chaque visite | **Corrigé** — date fixe (confidentialité, mentions légales) |
| « Effacer l'historique supprime aussi la session » : faux | **Corrigé** — la session et le cookie sont maintenant supprimés |
| Cookie décrit comme « UUID v7 » (c'est un jeton aléatoire) | **Corrigé** |
| Cache OFF « 7 jours » | **Corrigé** — 24 h |
| « Aucun transfert hors UE / base en UE » | **À vérifier** — région du service et de Postgres dans Railway (société américaine) |
| Contact `contact@monavispro.fr` sur NutriPetit | **À trancher** — adresse d'un autre projet |
| Restauration de sauvegarde testée | **Au déploiement** |

# Outil fourni

`bin/audit-prod.sh [URL]` : environ 50 contrôles en lecture seule sur la prod. Il couvre les pages, les en-têtes, `/app`, le SEO, l'API, le cookie, le CSRF, l'admin et le limiteur. Il affiche OK/KO, et son code de sortie est le nombre de KO.

# Reste ouvert

1. **Lancer `bin/audit-prod.sh`** après le déploiement de la branche d'audit.
2. **Âge inconnu** : appliquer ou non les règles 0-12 mois (sel).
3. **Score des produits sans ingrédients** : le plafonner, ou seulement le signaler.
4. **PHPStan** : corriger l'erreur pour remettre la CI au vert.
5. **E921** dans la table des additifs.
6. **Région Railway** et adresse de contact dans la politique de confidentialité.
7. Non fait dans cette passe : mobile à 5 largeurs, test de charge, accessibilité complète, restauration de sauvegarde.
