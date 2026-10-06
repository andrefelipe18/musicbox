# Musicbox

Monorepo do Musicbox: catálogo de lançamentos musicais (YouTube Music) com API, painel admin e app mobile.

## Estrutura do repositório

- `backend/` — API Laravel + painel admin Filament. Todos os comandos (`composer`, `php artisan`, `bun`) rodam dentro desta pasta.
- `mobile/` — app Flutter (ainda não criado; só o README).
- `design/` — design system ([`design/DESIGN.md`](design/DESIGN.md)); consultar antes de estilizar qualquer tela. O protótipo do app mobile (telas, fluxo, ligação com a API) está em [`design/mobile-prototype.md`](design/mobile-prototype.md).
- `docs/` — documentação técnica ([`docs/music-catalog.md`](docs/music-catalog.md), [`docs/scramble-implementation-report.md`](docs/scramble-implementation-report.md)).
- `assets/` — imagens de referência.

O `mobile/` consome a mesma API do `backend/`; não criar endpoints exclusivos para um cliente só.

## Commits

- Nunca adicionar trailer de co-autoria (`Co-Authored-By`) nem `Claude-Session` nos commits, nem rodapé de atribuição em PRs.
