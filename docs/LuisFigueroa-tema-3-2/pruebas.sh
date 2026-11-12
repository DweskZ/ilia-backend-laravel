#!/usr/bin/env bash
# IS-803 · Tema 3.2 · Luis Figueroa — Message Filter + Content-Based Router sobre RabbitMQ real.
#
# Terminales:  (1) php artisan serve --port=8080
#              (2) php artisan luisfigueroa:consumir-eventos --destino=revision
#              (3) php artisan luisfigueroa:consumir-eventos --destino=notificaciones
#              (4) php artisan luisfigueroa:consumir-eventos --destino=intentos
#              (5) este script   (antes, una vez: php artisan luisfigueroa:topologia-eventos)
# Uso:  ./pruebas.sh          # P1..P6 y resumen
#       ./pruebas.sh P4       # un solo caso
#       ./pruebas.sh Q        # colas del broker       ./pruebas.sh R   # tabla evento_ruta
cd "$(dirname "$0")/../.." || exit 1
BASE="${BASE:-http://127.0.0.1:8080}"
DB="${DB_FILE:-database/demo.sqlite}"
CONTENEDOR=luisfigueroa-rabbitmq
J=(-H 'Content-Type: application/json' -H 'Accept: application/json')

envval() { local v="${!1}"; [ -n "$v" ] && { echo "$v"; return; }; bat -p .env.example | rg -o "^$1=(.*)$" -r '$1'; }
PASS=$(envval LUISFIGUEROA_DEMO_PASSWORD)
ENTREGA=66666666-6666-4666-8666-666666666666
# Los eventos los publica el instructor (admin): un docente recibe 403.
TOKEN=$(curl -s "${J[@]}" -X POST "$BASE/api/auth/token" -d "{\"email\":\"instructor.demo@ilia.test\",\"password\":\"$PASS\"}" | jq -r .access_token)
[ -z "$TOKEN" ] || [ "$TOKEN" = null ] && { echo "No se obtuvo token (¿servidor en $BASE, BD sembrada o límite de 10 logins/min?)"; exit 1; }
A=(-H "Authorization: Bearer $TOKEN")

uuid() { uuidgen | tr 'A-Z' 'a-z'; }
Q() {
  echo '$ docker exec luisfigueroa-rabbitmq rabbitmqctl list_queues name messages_ready messages_unacknowledged consumers'
  docker exec $CONTENEDOR rabbitmqctl list_queues name messages_ready messages_unacknowledged consumers 2>/dev/null | rg 'name|ilia.entregas' | sort -r
}
colas_listas() { # nombre y mensajes listos de cada cola
  docker exec $CONTENEDOR rabbitmqctl list_queues name messages_ready 2>/dev/null | rg 'ilia\.entregas' | sort | column -t
}
tabla() { # $1 evento_id
  echo "\$ sqlite3 $DB  (tabla evento_ruta)"
  sqlite3 -header -column "$DB" "select substr(evento_id,1,8) evento, tipo, decision, replace(coalesce(destino,'-'),'ilia.entregas.','') destino, coalesce(consumido_por,'-') consumido_por from evento_ruta where evento_id='$1'"
}
espera() { # espera hasta 8 s a que algún consumidor registre el evento
  for _ in $(seq 1 16); do
    [ -n "$(sqlite3 "$DB" "select consumido_por from evento_ruta where evento_id='$1' and consumido_por is not null")" ] && return 0; sleep 0.5
  done; echo "(sin consumidor activo para este destino: el mensaje espera en su cola)"; return 1
}
enviar() { # $1 etiqueta  $2 cuerpo JSON → imprime petición y respuesta (el id va en $ID)
  echo "POST /api/eventos/entregas   [Bearer ****]"; echo "$2" | jq -c .
  curl -s -i "${J[@]}" "${A[@]}" -X POST "$BASE/api/eventos/entregas" -d "$2" | rg -v '^(Date|Host|Connection|X-Powered|Access-Control|Cache-Control|X-Content|Content-Type)'
  echo
}
evento() { # $1 id $2 tipo $3 prioridad $4 estado
  echo "{\"id\":\"$1\",\"correlation_id\":\"evt-$(echo "$1" | cut -c1-8)-corr\",\"tipo\":\"$2\",\"prioridad\":\"$3\",\"estado\":\"$4\",\"datos\":{\"entrega_id\":\"$ENTREGA\"}}"
}
ruta_ok() { # $1 titulo $2 tipo $3 prioridad
  echo "### $1"
  local id; id=$(uuid)
  enviar x "$(evento "$id" "$2" "$3" activo)"
  espera "$id"; tabla "$id"; echo; Q
}

P1() { ruta_ok "P1 — entrega.recibida → ilia.entregas.revision (consumidor revision)" entrega.recibida alta; }
P2() { ruta_ok "P2 — entrega.calificada → ilia.entregas.notificaciones (consumidor notificaciones)" entrega.calificada media; }
P3() { ruta_ok "P3 — entrega.reintento_solicitado → ilia.entregas.intentos (consumidor intentos)" entrega.reintento_solicitado baja; }

P4() {
  echo "### P4 — Tipo desconocido → ilia.entregas.no_enrutables (ningún consumidor lo toma)"
  local id; id=$(uuid)
  enviar x "$(evento "$id" entrega.tipo_inventado media activo)"
  tabla "$id"; echo; Q
}

P5() {
  echo "### P5 — Mensaje filtrado (entrega anulada): no llega a ningún consumidor y queda registrado"
  echo '(colas antes: nombre / mensajes listos)'; colas_listas
  local id; id=$(uuid)
  echo; enviar x "$(evento "$id" entrega.recibida alta anulada)"
  sleep 2; tabla "$id"
  echo; echo '(colas después: sin cambios, el evento nunca se publicó)'; colas_listas
}

P6() {
  echo "### P6 — Evento inválido (faltan prioridad y datos) → 422"
  local id; id=$(uuid)
  enviar x "{\"id\":\"$id\",\"tipo\":\"entrega.recibida\",\"estado\":\"activo\"}"
  echo "filas registradas para ese id: $(sqlite3 "$DB" "select count(*) from evento_ruta where evento_id='$id'")"
}

R() {
  echo "### R — Registro de enrutamiento (últimos eventos)"
  sqlite3 -header -column "$DB" "select substr(evento_id,1,8) evento, tipo, decision, replace(coalesce(destino,'-'),'ilia.entregas.','') destino, coalesce(consumido_por,'-') consumido_por, substr(coalesce(motivo,''),1,38) motivo from evento_ruta order by id desc limit 10"
}

if [ $# -eq 0 ]; then P1; echo; P2; echo; P3; echo; P4; echo; P5; echo; P6; echo; R; else for c in "$@"; do "$c"; echo; done; fi
