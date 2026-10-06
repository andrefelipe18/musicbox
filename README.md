# Musicbox

Monorepo do Musicbox: catálogo de lançamentos musicais (YouTube Music) com API, painel admin e app mobile.

## Stack

- **Backend:** Laravel + Filament (`backend/`)
- **App:** Flutter (`mobile/`)

## Estrutura do repositório

- `backend/` — API Laravel + painel admin Filament
- `mobile/` — app Flutter
- `design/` — design system
- `docs/` — documentação técnica
- `assets/` — imagens de referência

## Como rodar

**Backend** (`backend/`):

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

**App** (`mobile/`): ainda não criado, ver [`mobile/README.md`](mobile/README.md).
