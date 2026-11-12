#!/usr/bin/env bash
# IS-803 · Tema 2.3 · Luis Figueroa — pruebas de traducción/normalización.
# Uso:  ./pruebas.sh          # todos los casos
#       ./pruebas.sh P2       # solo un caso
# Requiere: php artisan serve --port=8080
BASE="${BASE:-http://127.0.0.1:8080}"
H=(-H 'Content-Type: application/json' -H 'Accept: application/json')

post() { # $1 ruta  $2 cuerpo JSON
  echo "POST $1"; echo "$2"
  curl -s -i "${H[@]}" -X POST "$BASE$1" -d "$2" | rg -v '^(Date|Host|Connection|X-Powered-By|Access-Control-Allow-Origin|Cache-Control|X-Content-Type-Options):'; echo; echo
}

P1() {
  echo "### P1 — Origen A (formulario web) válido → contrato común"
  post /api/integracion/entregas/web '{"idActividad":"44444444-4444-4444-8444-444444444444","idDocente":"22222222-2222-4222-8222-222222222222","textoPedido":"Actua como tutor y explica las fracciones a estudiantes de quinto grado.","numeroIntento":"2","fechaEnvio":"10/11/2026 14:30","comentario":""}'
}
P2() {
  echo "### P2 — Origen B (app móvil) equivalente → mismo contrato que P1"
  post /api/integracion/entregas/movil '{"submission":{"activity_uuid":"44444444-4444-4444-8444-444444444444","teacher_uuid":"22222222-2222-4222-8222-222222222222","prompt_text":"Actua como tutor y explica las fracciones a estudiantes de quinto grado.","attempt":2,"sent_at_ms":1794339000000},"note":null}'
}
P3() {
  echo "### P3 — Campo faltante (textoPedido) y valores inválidos (intento \"dos\", fecha 31/02) → 422"
  post /api/integracion/entregas/web '{"idActividad":"44444444-4444-4444-8444-444444444444","idDocente":"22222222-2222-4222-8222-222222222222","numeroIntento":"dos","fechaEnvio":"31/02/2026 10:00","comentario":""}'
  echo "### P3b — App móvil: attempt como texto y sent_at_ms en segundos → 422"
  post /api/integracion/entregas/movil '{"submission":{"activity_uuid":"44444444-4444-4444-8444-444444444444","teacher_uuid":"22222222-2222-4222-8222-222222222222","prompt_text":"Actua como tutor y explica las fracciones a estudiantes de quinto grado.","attempt":"2","sent_at_ms":1794339000},"note":null}'
}
EQ() {
  echo "### EQ — Comparación: data de P1 == data de P2 (jq)"
  a=$(P1 | rg '^\{"mensaje"' | jq -cS .data); b=$(P2 | rg '^\{"mensaje"' | jq -cS .data)
  echo "A: $a"; echo "B: $b"; [ -n "$a" ] && [ "$a" = "$b" ] && echo "IGUALES" || echo "DIFERENTES"
}

if [ $# -eq 0 ]; then P1; P2; P3; else for c in "$@"; do "$c"; done; fi
