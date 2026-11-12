#!/usr/bin/env bash
# IS-803 · Tema 3.1 · Luis Figueroa — comunicación asíncrona con RabbitMQ real.
#
# Terminales:  (1) php artisan serve --port=8080
#              (2) php artisan luisfigueroa:consumir-revisiones      # el consumidor
#              (3) este script
# Uso:  ./pruebas.sh            # P1, P2, P3, P4 y T (P2 pide detener/iniciar el consumidor)
#       ./pruebas.sh P4         # un solo caso
#       ./pruebas.sh Q          # estado de la cola en RabbitMQ
#       NO_PAUSE=1 ./pruebas.sh P2   # sin pausas interactivas
cd "$(dirname "$0")/../.." || exit 1
BASE="${BASE:-http://127.0.0.1:8080}"
DB="${DB_FILE:-database/demo.sqlite}"
LOG=storage/logs/luisfigueroa-revisiones.log
CONTENEDOR=luisfigueroa-rabbitmq
J=(-H 'Content-Type: application/json' -H 'Accept: application/json')

envval() { # valor de una variable: entorno o .env.example (valores ficticios)
  local v="${!1}"; [ -n "$v" ] && { echo "$v"; return; }
  bat -p .env.example | rg -o "^$1=(.*)$" -r '$1'
}
PASS=$(envval LUISFIGUEROA_DEMO_PASSWORD)
RUSER=$(envval LUISFIGUEROA_RABBITMQ_USER); RPASS=$(envval LUISFIGUEROA_RABBITMQ_PASSWORD)
ENTREGA=66666666-6666-4666-8666-666666666666   # entrega del docente demo
TOKEN=$(curl -s "${J[@]}" -X POST "$BASE/api/auth/token" -d "{\"email\":\"docente.demo@ilia.test\",\"password\":\"$PASS\"}" | jq -r .access_token)
[ -z "$TOKEN" ] || [ "$TOKEN" = null ] && { echo "No se obtuvo token (¿servidor en $BASE, BD sembrada o límite de 10 logins/min?)"; exit 1; }
A=(-H "Authorization: Bearer $TOKEN")

cola() { # estado de la cola en el broker
  echo '$ docker exec luisfigueroa-rabbitmq rabbitmqctl list_queues name messages_ready messages_unacknowledged consumers'
  docker exec $CONTENEDOR rabbitmqctl list_queues name messages_ready messages_unacknowledged consumers 2>/dev/null | rg -v '^(Timeout|Listing)'
}
log_de() { # líneas del log con ese correlation_id, en formato corto
  rg -F -e "$1" "$LOG" | perl -pe 's/^\[(\S+ \S+)\] \w+\.(\w+): ([^{]*?) ?\{"correlation_id":"([^"]*)".*$/$1 $2  $3  correlation_id=$4/'
}
fila() { # fila(s) de la tabla revision para ese correlation_id
  echo "\$ sqlite3 $DB  (tabla revision)"
  sqlite3 -header -column "$DB" "select substr(correlation_id,1,22) correlation_id, estado, puntaje, origen from revision where correlation_id like '$1'"
}
enviar() { # $1 correlation_id → imprime la respuesta completa
  echo "POST /api/revisiones   X-Correlation-ID: $1   [Bearer ****]"
  curl -s -i "${J[@]}" "${A[@]}" -H "X-Correlation-ID: $1" -X POST "$BASE/api/revisiones" -d "{\"entrega_id\":\"$ENTREGA\"}" | rg -v '^(Date|Host|Connection|X-Powered|Access-Control|Cache-Control|X-Content)'
  echo
}
estado() { curl -s "${J[@]}" "${A[@]}" "$BASE/api/revisiones/$1" | jq -r '.data.estado'; }
espera() { # espera hasta 25 s a que la revisión termine
  for _ in $(seq 1 50); do
    case "$(estado "$1")" in completada|fallida) return 0;; esac; sleep 0.5
  done; return 1
}
pausa() { [ -z "$NO_PAUSE" ] && read -rp ">>> $1 y pulse Enter... " _; }

P1() {
  echo "### P1 — Mensaje válido: 202 inmediato y el consumidor lo procesa (3 mensajes)"
  local ids=() i c ts; ts=$(date +%s)
  for i in a b c; do c="p1-$i-$ts"; ids+=("$c"); enviar "$c"; done
  echo "(esperando al consumidor...)"
  for i in "${ids[@]}"; do espera "$i" || echo "AVISO: $i sigue pendiente; ¿está corriendo el consumidor?"; done
  fila "p1-%-$ts"
  echo; echo "\$ log (productor y consumidor, mismo correlation_id)"; log_de "-$ts"
  echo; cola
}

P2a() {
  echo "### P2 — Consumidor detenido: el mensaje queda pendiente en la cola"
  [ -z "$NO_PAUSE" ] && echo "Detenga el consumidor (Ctrl+C en su terminal)."; pausa "Consumidor detenido"
  P2_ID="p2-$(date +%s)"; echo "$P2_ID" > /tmp/luisfigueroa-p2.id
  enviar "$P2_ID"
  cola
  echo; echo "GET /api/revisiones/$P2_ID → estado: $(estado "$P2_ID")  (pendiente: nadie lo consume)"
}
P2b() {
  P2_ID=$(cat /tmp/luisfigueroa-p2.id 2>/dev/null) || { echo "Ejecute antes P2a"; return 1; }
  echo "### P2 (cont.) — Se inicia el consumidor: el mensaje pendiente se procesa"
  pausa "Inicie el consumidor (php artisan luisfigueroa:consumir-revisiones)"
  espera "$P2_ID" || echo "AVISO: sigue pendiente"
  cola
  echo; fila "$P2_ID"; echo; log_de "$P2_ID"
}
P2() { P2a; echo; P2b; }

P3() {
  echo "### P3 — Mensajes inválidos: 422 y no se publica nada en la cola"
  cola | tail -1
  echo; echo "--- sin entrega_id"; curl -s -i "${J[@]}" "${A[@]}" -X POST "$BASE/api/revisiones" -d '{}' | rg '^(HTTP|\{)'
  echo; echo "--- entrega_id que no es UUID"; curl -s -i "${J[@]}" "${A[@]}" -X POST "$BASE/api/revisiones" -d '{"entrega_id":"abc"}' | rg '^(HTTP|\{)'
  echo; echo "--- entrega_id inexistente"; curl -s -i "${J[@]}" "${A[@]}" -X POST "$BASE/api/revisiones" -d '{"entrega_id":"99999999-9999-4999-8999-999999999999"}' | rg '^(HTTP|\{)'
  echo; cola | tail -1
  echo; echo "--- mensaje malformado puesto directamente en la cola (API de gestión): el consumidor lo rechaza"
  curl -s -u "$RUSER:$RPASS" -H 'content-type: application/json' -X POST "http://127.0.0.1:15672/api/exchanges/%2F/amq.default/publish" \
    -d '{"properties":{"delivery_mode":2},"routing_key":"ilia.revisiones","payload":"{\"foo\":\"no cumple el contrato\"}","payload_encoding":"string"}'; echo
  sleep 2; rg 'malformado' $LOG | tail -1 | perl -pe 's/^\[(\S+ \S+)\] \w+\.(\w+): /$1 $2  /'
  cola | tail -1
}

P4() {
  echo "### P4 — Correlación: mismo correlation_id en productor, consumidor y tabla"
  local c="p4-correl-$(date +%s)"
  enviar "$c"
  espera "$c" || echo "AVISO: sigue pendiente"
  echo "GET /api/revisiones/$c"; curl -s "${J[@]}" "${A[@]}" "$BASE/api/revisiones/$c" | jq -c .data; echo
  fila "$c"; echo
  echo "\$ rg \"$c\" $LOG"; log_de "$c"
}

T() {
  echo "### T — Tiempo de respuesta: síncrono vs asíncrono (retardo simulado de la revisión: 3 s)"
  local i t
  for i in 1 2 3; do
    t=$(curl -s -o /dev/null -w '%{time_total}' "${J[@]}" "${A[@]}" -X POST "$BASE/api/revisiones/sincronas" -d "{\"entrega_id\":\"$ENTREGA\"}")
    echo "síncrono  #$i  POST /api/revisiones/sincronas  200  ${t}s"
  done
  for i in 1 2 3; do
    t=$(curl -s -o /dev/null -w '%{http_code} %{time_total}' "${J[@]}" "${A[@]}" -X POST "$BASE/api/revisiones" -d "{\"entrega_id\":\"$ENTREGA\"}")
    echo "asíncrono #$i  POST /api/revisiones            ${t%% *}  ${t##* }s"
  done
}

Q() { cola; }

if [ $# -eq 0 ]; then P1; echo; P2; echo; P3; echo; P4; echo; T; else for c in "$@"; do "$c"; echo; done; fi
