# NutriPetit — Liste à faire complète (état au 10/06/2026)

## À INTÉGRER MAINTENANT (fichiers livrés dans ce dossier)

1. `src/EventSubscriber/SecurityHeadersSubscriber.php` — copier tel quel (autowiré)
2. `src/Command/PurgeScanSessionsCommand.php` — copier tel quel (RGPD 13 mois)
3. `src/Service/Product/DataCompletenessChecker.php` — copier tel quel
4. `templates/_partials/_disclaimer.html.twig` — copier tel quel

### Patchs manuels associés

5. ScanSession constructeur : `$this->userAgent = null !== $userAgent ? mb_substr($userAgent, 0, 500) : null;`
6. CI (`.github/workflows/ci.yml`) après "Validate composer.json" :
    ```yaml
    - name: Security audit des dépendances
      run: composer audit --no-dev || composer audit
    ```
7. Brancher DataCompletenessChecker dans ScanProductHandler::processScan()
   → retourner `'insufficientData' => bool` ; template : bandeau "Données
   insuffisantes pour noter ce produit" À LA PLACE du score si true.
   C'EST LE PLUS GROS DÉFAUT PRODUIT RESTANT (faux 100/100 sur produit vide).
8. Inclure `_partials/_disclaimer.html.twig` sous chaque affichage de score
    - styliser `.rr-disclaimer` dans public/css/style.css
9. Déplacer ton ChokingHazardEvaluatorTest de tests/Service/ → supprimer
   (remplacé par celui livré dans tests/Unit/)
10. Vérif finale : `cache:clear` + `phpstan analyse` + `phpunit` + commit

## 🔴 BUGS / DÉCISIONS À TRANCHER

11. ContaminatedFishEvaluator : matching sous-chaîne → faux positifs
    (`bar` matche "rhubarbe", "barre") → passer en regex word-boundary `\b`
12. Vérifier le même problème sur `ara` dans InfantFormulaScoreCalculator
    (matche "caramel") et tous les mots-clés courts des evaluators
13. DÉCISION : InfantFormulaScoreCalculator = base 100 plancher 70 (code actuel)
    vs base 60 (design initial). Trancher et documenter.
14. Sources : extraire les sources hardcodées d'InfantFormulaScoreCalculator
    vers la base (cohérence avec les autres règles) ; remplacer mpedia.fr par
    sources primaires (ANSES/EFSA) ; URLs profondes au lieu des homepages.

## TESTS RESTANTS

15. Fonctionnel ScannerControllerTest : GET /app/scan/{ean} → 200 / 404 / 503
    - 429 (rate limiter). Nécessite DB de test + fixture produit.
16. Golden tests : 3 vrais JSON OFF en fixtures (compote, lait, produit limite)
    → score exact attendu figé. Détecte TOUT changement de comportement.
17. Détecteurs : InfantFormulaDetectorTest, BabyProductDetectorTest,
    CriticalAlertDetectorTest
18. Extraire resolveBabyAgeMonths() dans une classe testable + test clamp 0–36
19. Tests cas limites supplémentaires evaluators : borne exacte seuils
    IronRich/Omega3 (valeur == seuil doit déclencher), nutriments en string

## DEPLOY RAILWAY (objectif portfolio)

20. trusted_proxies (sinon rate limiter = quota global via IP proxy) + vérifier
    le scheme https derrière le proxy
21. Cron quotidien : `php bin/console app:purge-scan-sessions`
22. PWA : manifest.json + service worker (l'app n'est pas installable sans)
23. robots.txt : noindex /app/, allow landing
24. Privacy policy : corriger mention "ULID" → "UUID" ; ajouter données
    réellement collectées (IP rate limiter, user-agent, âge bébé), durée 13 mois
25. Mentions légales : vérifier éditeur/hébergeur Railway/contact (LCEN) —
    contact@ générique, jamais l'adresse personnelle
26. Yuka → formulation générique dans les contenus

## AMÉLIORATIONS (après deploy, ordre de rentabilité)

27. README pro : screenshot mobile + schéma archi (scan→OFF→scoring→upsert)
    — 1h, le truc le plus rentable face aux recruteurs
28. Page produit publique /produit/{ean} + OG tags (SEO + partage parents)
29. Commande app:rescore (exploiter algoVersion — question d'entretien classique)
30. Circuit breaker OFF via Redis (3 échecs → court-circuit 60s)
31. Pondération des impacts par tranche d'âge (sel à 6 mois ≠ 30 mois)
32. Utiliser ingredients_analysis_tags OFF au lieu du matching texte brut
33. Limiter global sortant vers OFF (12/min, clé fixe Redis)
34. Durcir la CSP (extraire JS/CSS inline → supprimer unsafe-inline)
35. Section "modèle économique envisagé" dans le README (freemium : historique
    multi-appareils, comparateur, alternatives mieux notées, multi-enfants,
    export PDF pédiatre — jamais de vente de données ni de pub)

## À NE PAS FAIRE (anti-features portfolio)

Comptes utilisateurs, offline complet, i18n, GraphQL, microservices,
API Platform, paywall codé. Un portfolio se juge à ce qui est TERMINÉ.

## Légal — rappels

- Disclaimer visible obligatoire (protection dénigrement art. 1240 C. civ.)
- Jamais d'allégation santé ("bon pour la santé") — règlement UE 1924/2006
- Crédit ODbL visible sur chaque page produit (pas que les mentions légales)
- Pas de bandeau cookies nécessaire (cookies fonctionnels only, exemption CNIL)
  — ne JAMAIS ajouter d'analytics sans bandeau

## FRONT (audit 10/06)

36. 🔴 AVANT d'activer SecurityHeadersSubscriber : auto-héberger Poppins
    (woff2 dans assets/fonts/ + @font-face, supprimer l'@import Google Fonts
    de app.css et les 2 preconnect des DEUX layouts). Sinon la CSP bloque les
    polices. Bonus : règle le problème RGPD Google Fonts (transmission IP à
    Google, non conforme CNIL sans consentement).
37. og:image : SVG ignoré par FB/WhatsApp/LinkedIn → créer PNG 1200×630
    (public/images/og-cover.png) + ajouter og:url + twitter:card
38. apple-touch-icon PNG 180×180 dans les 2 layouts (iOS ignore le favicon SVG)
39. Ajouter <meta name="mobile-web-app-capable"> (apple-\* dépréciée, garder les 2)
40. Supprimer assets/icons/symfony.svg (reliquat squelette) + vérifier app.js
    et controllers.json pour restes du squelette
41. Lien <link rel="manifest"> à ajouter dans pwa.html.twig (avec item 22)

## 📷 SCANNER (à vérifier)

42. barcode_scanner_controller : vérifier que le flux caméra est bien stoppé
    dans disconnect() (sinon caméra reste active après navigation = fuite)
43. Vérifier debounce/verrou après détection (un code lu en rafale ne doit
    déclencher qu'une seule navigation/scan)

## ⚖️ RGPD — CORRECTIONS PRIVACY POLICY (le texte ne correspond plus au code)

44. 🔴 "L'âge n'est jamais transmis à nos serveurs" = FAUX depuis le fix âge :
    l'âge part en query param ?age= et est STOCKÉ en base
    (scan_sessions.baby_age_months + score_results.baby_age_months).
    Réécrire : "transmis au serveur pour le calcul du score et conservé
    associé à la session anonyme, 13 mois max".
45. 🔴 "Identifiant de session exclusivement dans le localStorage" = FAUX :
    c'est un COOKIE (ScanSessionCookieManager, httpOnly). Corriger la
    section 6 : cookie fonctionnel strictement nécessaire (exemption CNIL,
    pas de bandeau requis).
46. 🔴 "Aucune adresse IP stockée" : nuancer depuis le rate limiter — l'IP
    est traitée temporairement (compteur anti-abus, conservation ~1 minute,
    intérêt légitime sécurité). À mentionner dans les finalités.
47. "ULID" → "UUID v7" (déjà item 24, à faire en même temps que 44-46)
48. Ajouter le droit de réclamation auprès de la CNIL (cnil.fr) dans la
    section "Vos droits" — mention obligatoire absente.
49. Après corrections : relire la page entière vs le code réel (chaque
    affirmation technique doit être vérifiable dans le repo).

## Verdict RGPD global (après 44-49) : conforme.

Bases légales OK (intérêt légitime), minimisation réelle, pas de tracking,
durées définies + purge automatisée (item 2/21), droits listés, section
enfants présente. Le seul vrai écart est l'exactitude du texte vs le code.

### Pages d'erreur (l'utilisateur final les verra forcément)

50. Créer `templates/bundles/TwigBundle/Exception/error404.html.twig`,
    `error503.html.twig`, `error429.html.twig` et `error.html.twig` (fallback
    500). Étendre `base/landing.html.twig`, ton avec la marque ("Oups, ce
    produit s'est échappé du caddie"), bouton retour scanner. Sans ça, en
    prod l'utilisateur voit la page Symfony grise par défaut.
    Test : `APP_ENV=prod` local + URL bidon. Le 429 du rate limiter doit
    afficher le message "Trop de scans" — vérifier que
    TooManyRequestsHttpException rend bien error429.

### Infra prod

51. Healthcheck : route `GET /healthz` (controller minimal) qui fait
    `SELECT 1` via Doctrine et renvoie 200 JSON `{"status":"ok"}` / 503 sinon.
    La brancher dans Railway (Settings → Healthcheck Path) → redéploiement
    auto si l'app meurt. ~15 lignes.
52. Rate limiter storage : par défaut = cache.app (filesystem) → compteurs
    perdus à chaque deploy et non partagés si 2 instances. Ajouter dans
    rate_limiter.yaml : `cache_pool: cache.redis` (tu as déjà Redis) après
    avoir déclaré un pool Redis dans cache.yaml. 5 lignes.
53. Vérifier .env prod Railway : APP_SECRET fort généré (pas celui du repo),
    APP_DEBUG=0, et que le DATABASE_URL .env de fallback avec "!ChangeMe!"
    ne peut jamais être utilisé en prod.
54. importmap : `php bin/console asset-map:compile` doit être dans le build
    Railway (sinon assets servis en mode dev, lent). Vérifier le Dockerfile.

### Accessibilité (a11y) — 1h, différenciant en entretien

55. Audit rapide : `aria-label` sur les boutons icône-seuls de la bottom nav
    et du scanner ; `alt` sur toutes les images produit (nom du produit) ;
    contraste du texte atténué ≥ 4.5:1 (vérifier --np-text-muted) ; focus
    visible sur les liens/boutons (jamais `outline: none` sans remplacement).
    Le score couleur doit aussi être lisible sans la couleur (le label texte
    "Idéal/Déconseillé" suffit — vérifier qu'il est toujours affiché).
56. Lancer Lighthouse (Chrome DevTools) sur /app/scanner et la landing :
    viser ≥90 partout sauf PWA (tant que item 22 pas fait). Corriger ce qui
    sort. Screenshot du score Lighthouse = excellent contenu LinkedIn.

### Hygiène repo (vitrine recruteur)

57. Ajouter un fichier LICENSE (MIT recommandé pour un portfolio public).
58. History : vérifier qu'il y a une pagination ou un LIMIT côté contrôleur
    d'affichage (le repo limite, mais l'UI doit gérer "voir plus" au-delà).
59. Badge CI GitHub Actions + badge coverage dans le README (avec item 27).
60. `composer.json` : vérifier `"php": ">=8.4"` dans require (cohérence
    avec FrankenPHP prod) et que composer.lock est commité.

## ORDRE D'ATTAQUE RECOMMANDÉ (tout le fichier)

Session 1 : items 1-10 (intégration corrections livrées) + 36 (fonts) + 47
Session 2 : 44-46, 48-49 (RGPD) + 50 (pages erreur) + 11-12 (word boundaries)
Session 3 : 15 (fonctionnel scan) + 16 (golden tests) + 13 (décision base 70/60)
Session 4 : DEPLOY — 20-26 + 51-54 + 22 (PWA) + 37-39
Session 5 : 27 (README) + 56 (Lighthouse) + 28 (page publique) + post LinkedIn

## ANGLES MORTS (rien ne les couvrait encore)

61. 🔴 Seed des règles en PROD : ScoringRuleFixtures = dev only. Sans les 16
    règles en base prod, le moteur ne trouve RIEN → tous les scores = 100.
    Créer une commande idempotente `app:sync-scoring-rules` (upsert par code
    depuis ScoringRuleFixtures::getRules()) et la lancer au deploy (release
    command Railway). C'est BLOQUANT pour l'item 20-26.
62. 🔴 Données produit jamais rafraîchies : findByEan() renvoie le produit
    importé à vie. OFF évolue (reformulations, recettes changées) → pour une
    app bébé, un score sur des ingrédients périmés est un vrai problème.
    Dans ScanProductHandler : si updatedAt > 90 jours → re-fetch OFF et
    mettre à jour le produit (avec fallback silencieux sur la version locale
    si OFF est down). Nécessite un champ updatedAt sur Product s'il n'existe
    pas (+ migration).
63. Backups DB : vérifier que le plan Railway Postgres inclut des backups
    auto ; sinon cron pg_dump hebdo vers un volume/storage. Sans ça, un
    incident = perte totale de l'historique.
64. Monitoring erreurs prod : Sentry tier gratuit (sentry/sentry-symfony,
    DSN en variable d'env, 10 min). Sans ça, les 500 en prod sont invisibles
    — tu découvriras les bugs via les recruteurs qui testent.
65. sitemap.xml pour la landing (home, about, legal, privacy) + référencer
    dans robots.txt (complète l'item 23). Statique suffit : 4 URLs.

## COHÉRENCE TEXTES / CSS / JS (audit 10/06)

Vérifié OK : labels templates == enum mot pour mot ✅, ancienne échelle
95/85/75 absente des templates ✅, aucun TODO/FIXME/debugger dans le JS ✅.

66. 🔴 Labels dupliqués : \_score_circle.html.twig redéfinit en dur le mapping
    level→label ('ideal': 'Idéal pour bébé', ...) déjà porté par
    ScoreLevel::label(). Aujourd'hui synchro, mais à la première modif de
    l'enum les deux divergeront (exactement le bug d'échelle qu'on vient de
    corriger). Fix : exposer le label depuis le contrôleur/DTO (ou une
    extension Twig score_label(level, algo)) et supprimer le mapping Twig.
67. 🔴 index.html.twig utilise np-button / np-button--primary qui n'existent
    PAS en CSS (le design system définit np-btn). Boutons non stylés sur
    cette page → remplacer par np-btn ou supprimer index.html.twig si c'est
    un reliquat du squelette (la landing réelle est pages/landing/home).
    Vérifier quelle route rend index.html.twig.
68. np-pagination / np-pagination**link / np-pagination**info utilisés dans
    history.html.twig mais AUCUN style défini → pagination brute non stylée.
    Ajouter le composant dans assets/styles/components/ (item 58 lié).
69. Nettoyage CSS mort : ~90 classes définies jamais utilisées (np-age-badge,
    np-empty-state\_\_\*, np-app-history-list...). ATTENTION faux positifs :
    les modificateurs dynamiques Twig (np-...--{{ level }}) apparaissent
    comme "non utilisés" — vérifier au cas par cas avant suppression.
    Méthode : grep la classe dans templates/ + controllers/ avant de couper.
70. Supprimer le console.log du squelette dans assets/app.js ligne 10
    ("welcome to AssetMapper! 🎉" — pas très pro en prod, F12 ouvert).
71. controllers.json : ux-live-component et mercure-turbo-stream activés en
    fetch:"eager" alors qu'AUCUN live component n'est utilisé → JS chargé
    pour rien sur chaque page. Passer enabled:false (ou désinstaller les
    paquets symfony/ux-live-component si rien ne les utilise). Garder
    turbo-core si la navigation turbo est voulue — sinon le couper aussi
    (attention : turbo actif change le comportement des contrôleurs Stimulus
    au changement de page, à tester avec le scanner).

## COUVERTURE DE TESTS — bilan honnête (10/06)

TESTÉ (cœur métier, excellent) : 16 evaluators, ScoreCalculator,
InfantFormulaScoreCalculator, ScoreLevel, Ean13Validator, appliesToAge,
ScoreResult, garde-fou couverture.

NON TESTÉ (couche import/détection/orchestration) — à créer : 72. ProductImporterTest — LE PLUS IMPORTANT des manquants : parsing réponse
OFF → entité Product. Tester champs manquants, nutriments en string,
categories_tags absents, réponse partielle. (unit, mock du DTO OFF) 73. InfantFormulaDetectorTest + BabyProductDetectorTest : détection par
catégories/mots-clés, faux positif et faux négatif. (unit) 74. CriticalAlertDetectorTest : vérifier les alertes de sécurité déclenchées. 75. OpenFoodFactsClientTest : parsing réponse OK, produit absent (404 OFF),
OFF down (timeout → OpenFoodFactsUnavailableException). Mock HttpClient
(Symfony MockHttpClient). (unit) 76. resolveBabyAgeMonths : extraire dans une classe testable puis tester
clamp 0-36, absent→null, ?age=abc→0. (item 18, lié) 77. Extractors (priorité basse) : AdditiveExtractor, CarbonFootprintExtractor,
EnvironmentAnalyzer, MinimumAgeExtractor, AgeScoreSimulator,
NutrientViewBuilder, RuleSourceAggregator. 1 test nominal chacun. 78. Repositories : findActiveByVersion (filtre actif+version),
findForSessionAndProduct (upsert). Tests d'intégration (DB test).

### ScannerControllerTest (item 15) — prérequis AVANT de l'écrire

- composer require --dev dama/doctrine-test-bundle (rollback auto entre tests)
- .env.test : DATABASE_URL vers une DB nutripetit_test dédiée
- Cas 200 : persister un Product en base test → findByEan le trouve →
  OFF JAMAIS appelé (pas de mock réseau nécessaire). GET route scan → 200.
- Cas 404 : EAN absent + mock OpenFoodFactsClient qui lève ProductNotFound.
- Cas 503 : mock OFF qui lève OpenFoodFactsUnavailableException.
- Cas 429 : 16 requêtes rapides → la 16e renvoie 429 (attention au storage
  du rate limiter partagé entre tests → reset le cache pool en setUp).
- À écrire avec le vrai nom de route sous les yeux (app_pwa_scan ?).

## OBJECTIF couverture réaliste portfolio

Viser ~70-80% lignes sur src/Service (le métier), pas 100% partout.
Les contrôleurs/templates : 1 fonctionnel suffit. Générer le rapport :
vendor/bin/phpunit --coverage-text (nécessite Xdebug ou pcov)

## BUGS TROUVÉS PENDANT LES TESTS (10/06)

79. ScannerController::scan() ne catch pas InvalidArgumentException levée par
    findOrFetchProduct (EAN 13 chiffres mais checksum invalide) → 500 au lieu
    de 400/404. Ajouter un catch InvalidArgumentException → render scan_error
    en 404 (ou 400). Test à ajouter une fois corrigé.
80. (Testabilité) Extraire OpenFoodFactsClientInterface (port) +
    BabyProductDetectorInterface, type-hinter dessus dans le handler/controller,
    mocker l'interface au lieu de bypass-finals. Cohérent ports-and-adapters
    (conseil Billèle). bypass-finals = solution temporaire en attendant.
