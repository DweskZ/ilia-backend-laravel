# ILIA · Backend Laravel

Backend en Laravel 13 del proyecto de aula **ILIA**, una plataforma de formación docente. Expone la API que consumen el cliente web y la aplicación móvil.

Asignatura: Integración de Sistemas (IS-803), período 2026-2.

## Módulos

| Módulo | Autor | Documentación |
|---|---|---|
| Entregas de taller: contrato JSON (tema 2.2) | Luis Figueroa | [`docs/LuisFigueroa-tema-2-2/`](docs/LuisFigueroa-tema-2-2/README.md) |

## Requisitos

- PHP 8.3 o superior
- Composer
- SQLite

## Puesta en marcha

```bash
composer install
```

Crea un archivo `.env` en la raíz con al menos:

```
APP_NAME=ILIA
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
```

Luego:

```bash
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=LuisFigueroaDemoSeeder
php artisan serve
```

## Pruebas

```bash
php artisan test
```

El archivo `.env` y la base de datos local no se versionan.
