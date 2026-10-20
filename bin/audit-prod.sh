#!/usr/bin/env bash
#
# Audit de la prod NutriPetit, en lecture seule (sauf 1 scan et 1 session de test).
# Usage : bin/audit-prod.sh [https://nutripetit.up.railway.app]
#
# Chaque ligne affiche OK ou KO. Code de sortie = nombre de KO.
# Le test du limiteur envoie 17 scans : à lancer hors des heures de pointe.

set -u
BASE="${1:-https://nutripetit.up.railway.app}"
EAN_BEBE="3288131510927"   # Babybio Pomme-Mirabelle-Quetsche (produit de test connu)
EAN_ADULTE="3017620422003" # Nutella : doit être refusé (hors périmètre)
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
OK=0; KO=0

ok() { OK=$((OK + 1)); printf '  \033[32mOK\033[0m  %s\n' "$1"; }
ko() { KO=$((KO + 1)); printf '  \033[31mKO\033[0m  %s\n' "$1"; }
check() { if eval "$2"; then ok "$1"; else ko "$1"; fi; }

# get URL -> écrit $TMP/h (en-têtes) et $TMP/b (corps), renvoie le code HTTP
get() { curl -sS -o "$TMP/b" -D "$TMP/h" -w '%{http_code}' --max-time 20 "$@" 2>/dev/null; }
hdr() { grep -i "^$1:" "$TMP/h" | head -1 | cut -d' ' -f2- | tr -d '\r'; }

echo "== Pages publiques"
for p in / /a-propos /confidentialite /mentions-legales; do
  check "GET $p -> 200" '[ "$(get "$BASE$p")" = 200 ]'
done
check "page inconnue -> 404 (pas 500)" '[ "$(get "$BASE/nexiste-pas-$RANDOM")" = 404 ]'
check "page 404 sans trace d'erreur Symfony" '! grep -qi "stack trace\|Symfony Exception\|vendor/symfony" "$TMP/b"'
check "profiler coupé (/_profiler -> 404)" '[ "$(get "$BASE/_profiler")" = 404 ]'
check "http:// redirige vers https://" '[[ "$(curl -sS -o /dev/null -w "%{http_code} %{redirect_url}" --max-time 15 "${BASE/https:/http:}/")" =~ ^30[178]\ https:// ]]'

echo "== En-têtes (vitrine Twig)"
get "$BASE/" >/dev/null
check "CSP présente" '[ -n "$(hdr content-security-policy)" ]'
check "HSTS présent" '[ -n "$(hdr strict-transport-security)" ]'
check "X-Content-Type-Options nosniff" '[ "$(hdr x-content-type-options)" = nosniff ]'
check "X-Frame-Options DENY" '[ "$(hdr x-frame-options)" = DENY ]'
check "pas d'en-tête X-Powered-By" '[ -z "$(hdr x-powered-by)" ]'
check "pas d'en-tête Server" '[ -z "$(hdr server)" ]'

echo "== PWA Angular (/app)"
check "/app -> 301 vers /app/" '[ "$(get "$BASE/app")" = 301 ]'
check "/app/ -> 200" '[ "$(get "$BASE/app/")" = 200 ]'
check "CSP présente sur /app/" '[ -n "$(hdr content-security-policy)" ]'
check "HSTS présent sur /app/" '[ -n "$(hdr strict-transport-security)" ]'
check "nosniff sur /app/" '[ "$(hdr x-content-type-options)" = nosniff ]'
check "index.html non mis en cache" '[[ "$(hdr cache-control)" == *no-cache* ]]'
check "aucun appel à Google Fonts" '! grep -q "fonts.googleapis" "$TMP/b"'
check "lang=fr" 'grep -q "<html lang=\"fr\"" "$TMP/b"'
MAIN="$(grep -o 'main-[A-Za-z0-9]*\.js' "$TMP/b" | head -1)"
check "bundle principal en cache long" '[ -n "$MAIN" ] && get "$BASE/app/$MAIN" >/dev/null && [[ "$(hdr cache-control)" == *immutable* ]]'
for p in scanner historique saisie-manuelle profil-bebe infos scan/$EAN_BEBE; do
  check "accès direct /app/$p -> 200" '[ "$(get "$BASE/app/$p")" = 200 ]'
done

echo "== SEO"
check "robots.txt interdit /app/" '[ "$(get "$BASE/robots.txt")" = 200 ] && grep -q "Disallow: /app/" "$TMP/b"'
check "robots.txt : sitemap en https" 'grep -q "Sitemap: https://" "$TMP/b"'
check "sitemap : uniquement des URL https" '[ "$(get "$BASE/sitemap.xml")" = 200 ] && ! grep -q "<loc>http://" "$TMP/b"'
get "$BASE/" >/dev/null
check "og:image en https" 'grep -q "og:image\" content=\"https://" "$TMP/b"'

echo "== API"
check "GET /api/info -> 200 JSON" '[ "$(get "$BASE/api/info")" = 200 ] && [[ "$(hdr content-type)" == application/json* ]]'
check "algo 1.3.0 déployé" 'grep -q "\"algoVersion\":\"1.3.0\"" "$TMP/b"'
check "règle miel présente" 'grep -q "honey_botulism" "$TMP/b"'
check "GET /api/history sans cookie -> 200 vide" '[ "$(get "$BASE/api/history")" = 200 ] && grep -q "\"total\":0" "$TMP/b"'
check "EAN mal formé -> 404" '[ "$(get "$BASE/api/scan/abc")" = 404 ]'
check "EAN à clé fausse -> 400" '[ "$(get "$BASE/api/scan/1234567890123")" = 400 ]'
check "produit adulte -> 422 hors périmètre" '[ "$(get "$BASE/api/scan/$EAN_ADULTE")" = 422 ]'
check "produit bébé -> 200" '[ "$(get "$BASE/api/scan/$EAN_BEBE?age=9")" = 200 ]'
COOKIE="$(hdr set-cookie)"
check "cookie np_session posé" '[[ "$COOKIE" == np_session=* ]]'
check "cookie Secure" '[[ "${COOKIE,,}" == *secure* ]]'
check "cookie HttpOnly" '[[ "${COOKIE,,}" == *httponly* ]]'
check "cookie SameSite=Lax" '[[ "${COOKIE,,}" == *samesite=lax* ]]'
check "âge pris en compte (babyAgeMonths=9)" 'grep -q "\"babyAgeMonths\":9" "$TMP/b"'
check "effacement sans jeton CSRF -> 403" '[ "$(get -X POST "$BASE/api/history/clear")" = 403 ]'
check "pas de CORS ouvert à un site tiers" '[ "$(get -H "Origin: https://exemple-pirate.test" "$BASE/api/info" >/dev/null; hdr access-control-allow-origin)" = "" ]'

echo "== Admin"
check "/admin redirige vers la connexion" '[[ "$(get "$BASE/admin")" =~ ^30[12]$ ]] && [[ "$(hdr location)" == */admin/login* ]]'
check "/admin/login -> 200" '[ "$(get "$BASE/admin/login")" = 200 ]'

echo "== Limiteur de scans (15/min par IP)"
# 1) Un X-Forwarded-For inventé ne doit pas donner un nouveau quota.
LAST=""
for i in $(seq 1 17); do
  LAST="$(get -H "X-Forwarded-For: 203.0.113.$i" "$BASE/api/scan/$EAN_BEBE")"
done
check "X-Forwarded-For falsifié ignoré (17e scan -> 429)" '[ "$LAST" = 429 ]'
echo "     (2) Vérif manuelle : pendant que ce quota est épuisé, scanner depuis un"
echo "         autre réseau (4G du téléphone) doit marcher. Si c'est 429 aussi,"
echo "         le quota est global : la vraie IP n'arrive pas jusqu'à Symfony."

echo
echo "Bilan : $OK OK, $KO KO"
exit "$KO"
