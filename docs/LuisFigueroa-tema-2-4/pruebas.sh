#!/usr/bin/env bash
# IS-803 · Tema 2.4 · Luis Figueroa — seguridad en tránsito (Sanctum + Policy).
# Uso:  ./pruebas.sh          # todos los casos
#       ./pruebas.sh P3       # solo un caso
# Requiere: php artisan serve --port=8080 con la BD sembrada (LuisFigueroaDemoSeeder).
cd "$(dirname "$0")/../.." || exit 1
BASE="${BASE:-http://127.0.0.1:8080}"
J=(-H 'Content-Type: application/json' -H 'Accept: application/json')

# Contraseña ficticia de las cuentas demo: variable de entorno o valor de .env.example.
PASS="${LUISFIGUEROA_DEMO_PASSWORD:-$(bat -p .env.example | rg -o '^LUISFIGUEROA_DEMO_PASSWORD=(.*)$' -r '$1')}"

DOCENTE=docente.demo@ilia.test
ADMIN=instructor.demo@ilia.test
E_DOCENTE=66666666-6666-4666-8666-666666666666   # entrega del docente 1
E_OTRO=77777777-7777-4777-8777-777777777777      # entrega del docente 2
DOCENTE2=55555555-5555-4555-8555-555555555555

token() { # $1 email → imprime solo el token (no se muestra en pantalla)
  curl -s "${J[@]}" -X POST "$BASE/api/auth/token" -d "{\"email\":\"$1\",\"password\":\"$PASS\"}" | jq -r .access_token
}
login() { # $1 email → muestra la respuesta con el token enmascarado
  echo "POST /api/auth/token  (email=$1)"
  curl -s -i "${J[@]}" -X POST "$BASE/api/auth/token" -d "{\"email\":\"$1\",\"password\":\"$PASS\"}" \
    | limpia | mask
  echo; echo
}
limpia() { rg -v '^(Date|Host|Connection|X-Powered-By|Access-Control-Allow-Origin|Cache-Control|X-Content-Type-Options):'; }  # oculta cabeceras HTTP repetitivas
mask() { perl -pe 's/("access_token":")([^"|]*\|)?(.{6})[^"]*/$1$3…(oculto)/'; }
req() { # $1 token|- $2 método $3 ruta [$4 cuerpo]
  local tk="$1" m="$2" ruta="$3" body="$4" a=()
  [ "$tk" != "-" ] && a=(-H "Authorization: Bearer $tk")
  echo "$m $ruta   [Authorization: $([ "$tk" = "-" ] && echo 'sin token' || echo 'Bearer ****')]"
  if [ -n "$body" ]; then curl -s -i "${J[@]}" "${a[@]}" -X "$m" "$BASE$ruta" -d "$body" | limpia
  else curl -s -i "${J[@]}" "${a[@]}" -X "$m" "$BASE$ruta" | limpia; fi
  echo; echo
}

P1() {
  echo "### P1 — Autenticado y con permiso → 2xx"
  local td ta id out
  login $DOCENTE
  td=$(token $DOCENTE); ta=$(token $ADMIN)
  echo "--- docente: lista solo sus entregas"
  req "$td" GET /api/entregas
  echo "--- docente: ve su propia entrega"
  req "$td" GET /api/entregas/$E_DOCENTE
  echo "--- docente: registra su entrega (docente_id = su propio id)"
  echo "POST /api/entregas   [Authorization: Bearer ****]"
  out=$(curl -s -i "${J[@]}" -H "Authorization: Bearer $td" -X POST "$BASE/api/entregas" -d '{"actividad_id":"44444444-4444-4444-8444-444444444444","docente_id":"22222222-2222-4222-8222-222222222222","pedido":"Actua como tutor y explica las fracciones a estudiantes de quinto grado.","intento":2,"enviado_en":"2026-11-10T14:30:00-05:00","observacion":null}')
  echo "$out" | limpia; echo; echo
  id=$(echo "$out" | tail -1 | jq -r .data.entrega_id)
  echo "--- admin: elimina esa entrega (DELETE solo admin)"
  req "$ta" DELETE /api/entregas/$id
}

P2() {
  echo "### P2 — Sin autenticación → 401"
  req - GET /api/entregas
  req - GET /api/entregas/$E_DOCENTE
  req - POST /api/entregas '{}'
  echo "--- token inválido (no se repite en la respuesta)"
  req '999|token-falso' GET /api/entregas
}

P3() {
  echo "### P3 — Autenticado sin permiso → 403"
  local td; td=$(token $DOCENTE)
  echo "--- docente 1 intenta ver la entrega de otro docente"
  req "$td" GET /api/entregas/$E_OTRO
  echo "--- docente 1 intenta DELETE (solo admin)"
  req "$td" DELETE /api/entregas/$E_DOCENTE
  echo "--- docente 1 intenta entregar a nombre del docente 2"
  req "$td" POST /api/entregas "{\"actividad_id\":\"44444444-4444-4444-8444-444444444444\",\"docente_id\":\"$DOCENTE2\",\"pedido\":\"Actua como tutor y explica las fracciones a estudiantes de quinto grado.\",\"intento\":2,\"enviado_en\":\"2026-11-10T14:30:00-05:00\",\"observacion\":null}"
}

P4() {
  echo "### P4 — Revisión de configuración (secretos fuera del repositorio)"
  echo '$ git check-ignore -v .env'
  git check-ignore -v .env
  echo; echo '$ git ls-files | rg "\.env"      # solo debe aparecer .env.example'
  git ls-files | rg '\.env'
  echo; echo '$ nombres de variables de .env.example (sin valores)'
  bat -p .env.example | rg -o '^[A-Z][A-Z0-9_]*(?==)' --pcre2 | sort | tr '\n' ' '; echo
  echo; echo '$ rg: contraseñas/tokens/claves literales en el código'
  if rg -n -i --glob '!vendor' --glob '!.env*' -e "(password|passwd|secret|api[_-]?key|token)['\"]?\s*(=>|=|:)\s*['\"][^'\"\$]{8,}['\"]" app config routes database bootstrap; then
    echo "ATENCIÓN: hay coincidencias"; else echo "OK: sin coincidencias (exit 1 de rg = nada encontrado)"; fi
  echo; echo '$ rg: llamadas a Log:: que mencionen password/token'
  if rg -n -i 'Log::.*(password|token)' app; then echo "ATENCIÓN"; else echo "OK: ningún log de credenciales"; fi
}

if [ $# -eq 0 ]; then P1; P2; P3; P4; else for c in "$@"; do "$c"; done; fi
