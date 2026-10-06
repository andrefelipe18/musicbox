# Catálogo musical — instalação e API

Guia operacional do backend de catálogo musical. Serviço usa SQLite, fila e cache no banco; autenticação de SPA usa sessão Sanctum e CSRF. Não há frontend, OAuth/token endpoint ou player neste escopo.

## Instalação local

Projeto declara SQLite como padrão (`DB_CONNECTION=sqlite`). Configure `.env` a partir de `.env.example`, gere chave da aplicação e garanta que `database/database.sqlite` exista:

```sh
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

No PowerShell, use `Copy-Item .env.example .env` e `New-Item -ItemType File database/database.sqlite -Force` no lugar de `cp`/`touch`.

`php artisan migrate` cria somente tabelas pendentes; não apaga dados existentes. Banco é tratado como descartável pelo projeto, mas instalação não requer reset. **Não use `migrate:fresh` como passo normal**: ele apaga tabelas e registros. Migrations e testes de domínio usam DDL/checks específicos do SQLite; não troque driver sem adaptar e testar o schema. Testes usam banco efêmero configurado pelo projeto.

Defaults de sessão, fila, cache e música vêm de `.env.example`/`config/*`. As variáveis `MUSIC_*` e `YOUTUBE_MUSIC_*` abaixo são overrides opcionais lidos por `config/music.php`; não estão todas predefinidas em `.env.example`:

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=null
QUEUE_CONNECTION=database
CACHE_STORE=database

# Origens exatas, separadas por vírgula; sem wildcard com credenciais.
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
# Hosts/portas, sem esquema. A configuração também deriva hosts das origens CORS.
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000

MUSIC_CATALOG_TTL=21600
MUSIC_SEARCH_TTL=3600
MUSIC_METADATA_TTL=604800
MUSIC_LOCK_SECONDS=300
MUSIC_STUCK_AFTER_SECONDS=900
MUSIC_REQUEST_BUDGET_SECONDS=60
YOUTUBE_MUSIC_HL=en
YOUTUBE_MUSIC_GL=US
YOUTUBE_MUSIC_TIMEOUT=10
YOUTUBE_MUSIC_CONNECT_TIMEOUT=5
YOUTUBE_MUSIC_RETRIES=2
YOUTUBE_MUSIC_RETRY_DELAY_MS=250
YOUTUBE_MUSIC_MAX_RETRY_AFTER_SECONDS=5
YOUTUBE_MUSIC_MAX_PAGES=100
YOUTUBE_MUSIC_MAX_DURATION_SECONDS=60
DB_QUEUE_RETRY_AFTER=90
```

`DB_CONNECTION`, `CACHE_STORE=database` e `QUEUE_CONNECTION=database` devem apontar ao mesmo banco compartilhado entre aplicação e workers. Migrations criam tabelas `sessions`, `cache`, `cache_locks`, `jobs` e `failed_jobs`. Cache `array` serve a testes isolados, não coordena workers/processos. Busca tem cache separado por consulta normalizada, idioma e região; TTL padrão **1 hora**. Catálogo completo de artista tem TTL **6 horas**. Uma sincronização completa vazia é válida e distinta de nunca sincronizada.

Idioma/região padrão é `hl=en`, `gl=US`; para português do Brasil configure `YOUTUBE_MUSIC_HL=pt` e `YOUTUBE_MUSIC_GL=BR`. Parser de seções dá suporte a rótulos em inglês e português; idioma não suportado falha explicitamente. Transporte restringe endpoint a HTTPS `music.youtube.com/youtubei/v1`, mantém validação TLS e não segue redirects. Retries são limitados a falhas transitórias/429/5xx e `Retry-After` é limitado pelo teto configurado.

### Sessões em ambientes HTTPS

- Em desenvolvimento SPA e API podem usar `localhost` com portas diferentes. Mantenha o mesmo hostname nas URLs, no `Origin` e no jar de cookies; `localhost` e `127.0.0.1` são hosts/cookies distintos.
- Em produção use HTTPS e `SESSION_SECURE_COOKIE=true`. Para SPA e API em subdomínios do mesmo domínio, configure, por exemplo, `SESSION_DOMAIN=.example.com`, inclua a origem exata da SPA em `CORS_ALLOWED_ORIGINS` e seu host em `SANCTUM_STATEFUL_DOMAINS` (`app.example.com`, sem esquema). Deixe `SESSION_DOMAIN=null` quando cookie host-only for suficiente.
- CORS permite credenciais e origens explícitas. Não configure `*`. Cookie de sessão é `HttpOnly`; cookie `XSRF-TOKEN` é legível pelo cliente para montar header. `statefulApi()` usa `SameSite=Lax`; prefira SPA e API no mesmo site registrável. No browser envie `credentials: 'include'` e `X-XSRF-TOKEN: decodeURIComponent(cookie XSRF-TOKEN)`. Políticas de cookies de terceiros podem bloquear arquiteturas cross-site mesmo com CORS correto.
- `GET /sanctum/csrf-cookie` deve preceder cadastro/login e demais mutações stateful. Envie cookies com credenciais e header `X-XSRF-TOKEN` contendo valor URL-decoded do cookie `XSRF-TOKEN`. CSRF continua ativo; Origin ausente ou cujo host/porta não está na lista stateful é recusado no cadastro/login/logout antes da mutação. Para browser, configure também origem exata em CORS; CORS e Sanctum stateful são controles distintos.

Exemplo curl, shell POSIX. Jar conserva cookie de sessão; valor CSRF do cookie jar precisa ser URL-decoded antes de usar como header:

```sh
API=http://localhost:8000
ORIGIN=http://localhost:3000
COOKIE_JAR=/tmp/music-cookies.txt
: > "$COOKIE_JAR"

csrf() {
  curl -fsS -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -H "Origin: $ORIGIN" "$API/sanctum/csrf-cookie" >/dev/null
  XSRF_ENCODED=$(awk '$6 == "XSRF-TOKEN" { print $7 }' "$COOKIE_JAR")
  XSRF_TOKEN=$(php -r 'echo rawurldecode($argv[1]);' "$XSRF_ENCODED")
}

csrf
curl -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -H "X-XSRF-TOKEN: $XSRF_TOKEN" \
  --data '{"name":"Listener","email":"listener@example.test","password":"Strong-password-123","password_confirmation":"Strong-password-123"}' \
  "$API/api/v1/register"

curl -i -b "$COOKIE_JAR" -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  "$API/api/v1/me"

csrf
curl -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  -H "X-XSRF-TOKEN: $XSRF_TOKEN" -X POST "$API/api/v1/logout"
```

Para entrar em conta existente, use mesmo fluxo `csrf`, troque cadastro por `POST /api/v1/login` com `{"email":"...","password":"..."}` e depois consulte `/api/v1/me`. Não registre nem compartilhe o jar: ele autentica a sessão.

## Worker e sincronização

Em terminal separado mantenha worker ativo para processar sincronizações enfileiradas:

```sh
php artisan queue:work database --queue=default --timeout=70 --tries=5 --sleep=1
```

Defaults atuais: job tem timeout **70 s**, até **5 tentativas** e backoff `[10, 30, 60, 120]`; queue database tem `retry_after=90 s`. Configure `retry_after` maior que timeout do worker/job; mantenha `--timeout` alinhado ao timeout do job. Cache e queue devem ser compartilhados entre aplicação e workers. Com os defaults, ambos usam o mesmo SQLite, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` e locks do cache database.

O tempo máximo cooperativo de uma sincronização inteira usa `MUSIC_REQUEST_BUDGET_SECONDS` (**60 s**), compartilhado por browse, páginas, detalhes e persistência. `YOUTUBE_MUSIC_MAX_DURATION_SECONDS` também limita a coleta do provider; chamadas aninhadas respeitam menor deadline ativo. Timeout HTTP, pausas de retry e espera SQLite ficam limitados pelo orçamento restante. Verificações entre etapas e dentro da transação impedem continuar ou iniciar commit após expirar; um `COMMIT` SQLite já iniciado não pode ser interrompido atomicamente e sua duração física depende do driver/armazenamento. Depois de confirmado o commit, a sincronização permanece bem-sucedida mesmo se o orçamento expirar ou ocorrer falha na limpeza do estado temporário; dados duráveis não são classificados como uma atualização malsucedida.

`MUSIC_LOCK_SECONDS=300` é lease dos locks de enqueue/sync, **não** o TTL de unicidade. `SyncArtistCatalogJob::uniqueFor` calcula um horizonte de **1.232 s** nos defaults: `70×5` de execução + `90×4` de reservas `retry_after` + `10+30+60+120` de backoff + lease de `300 s` e margens de segurança. Se alterar timeout, tentativas/backoff, `retry_after` ou lock, job recalcula horizonte; `retry_after` não pode ser menor/igual ao timeout do job.

Uma geração por enqueue é mantida no cache compartilhado por `uniqueFor + MUSIC_STUCK_AFTER_SECONDS` (**2.132 s** por default). Só job/callback da geração atual e estado ainda `queued`/`running` podem alterar falha; callbacks antigos não sobrescrevem sucesso ou enqueue novo. Lock de sync ocupado torna job deferred e reprograma para depois do lease (**301 s** no default), em vez de reportar sucesso.

Recuperação verifica `last_sync_attempt_at`, não `updated_at`: `MUSIC_STUCK_AFTER_SECONDS=900` permite superseder `queued`/`running` antigos. Recuperação substitui a geração e libera lock unique anterior; job atrasado da geração antiga é ignorado quando iniciar. Com worker encerrado durante uma tentativa, queue reserva job até `retry_after` antes de nova entrega; se sync lock ainda estiver ocupado, tentativa fica deferred. Em morte não recuperável/perda de fila, o limiar de estado stuck permite novo enqueue. Implemente retry/reservation/locks em stores compartilhados: cache `array` não coordena processos.

O catálogo sob `/artists/{id}/releases` pode enfileirar sincronização automaticamente:

- Nunca sincronizado: dados locais podem estar vazios; resposta inicial `202 Accepted`, `meta.sync.pending=true`, enquanto job aguarda worker.
- Expirado: API continua servindo lançamentos locais (`200 OK`) e marca `meta.sync.stale=true`; uma atualização fica pendente.
- Catálogo completo atual, inclusive catálogo legitimamente vazio: `200 OK`, sem provider call ou enqueue.
- Sincronização incompleta/inválida falha fechada; dados locais anteriores e ratings pessoais não são substituídos por coleta parcial.

Comando chama o mesmo sincronizador de forma síncrona. Argumento é **ULID interno do artista**, não ID externo YouTube Music:

```sh
php artisan music:sync-artist 01J...ULID... --force
```

Sem `--force`, sincronização fresca não é repetida. `--force` força atualização; resultado informa inseridos, atualizados e ignorados. ID inexistente ou falha retorna código de erro com mensagem genérica. Comando registrado e help verificados: `php artisan help music:sync-artist`.

`MUSIC_METADATA_TTL=604800` limita nova busca de detalhes durante sync quando `metadata_synced_at` está válido. O endpoint local de release continua lendo banco; TTL não habilita refresh remoto sob demanda nessa rota.

## Fluxo de uso: buscar Froid e escolher artista

Depois de autenticar via sessão:

```sh
curl -i -b "$COOKIE_JAR" -G \
  -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  --data-urlencode 'q=Froid' "$API/api/v1/artists/search"
```

Busca exige `q` string com **2–120 caracteres**, trim aplicado. Resposta pode conter vários candidatos. **Escolha manualmente** o registro correto e copie `data[n].id` (ULID interno); API nunca seleciona automaticamente o primeiro resultado. Não use `youtube_music_id` nem browse ID como `artist_id` nas rotas locais.

```sh
ARTIST_ID='copie-aqui-o-id-interno-escolhido'
curl -i -b "$COOKIE_JAR" -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  "$API/api/v1/artists/$ARTIST_ID"
curl -i -b "$COOKIE_JAR" -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  "$API/api/v1/artists/$ARTIST_ID/releases"
```

Detalhe de artista retorna estado `sync_status` e `catalog_synced_at`. Busca só descobre/persiste candidatos básicos; não significa que discografia está sincronizada. Primeiro acesso à discografia pode retornar 202 + lista local vazia até worker terminar. Consulte novamente. Resposta stale pode trazer catálogo anterior útil com `200`, `meta.sync.stale=true` e `pending=true`; não confunda vazio pendente com vazio completo.

Para pedir refresh explicitamente, POST precisa sessão, Origin stateful e CSRF válidos. O Form Request aceita `force` boolean opcional; endpoint usa `true` como padrão manual:

```sh
csrf
curl -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -H "X-XSRF-TOKEN: $XSRF_TOKEN" \
  --data '{"force":true}' "$API/api/v1/artists/$ARTIST_ID/sync"
```

Resposta do enqueue é 202; consulte a discografia após processamento. Também é possível chamar comando Artisan acima com ULID interno.

## Rotas e paginação

Todas as rotas API usam `/api/v1`; estado atual foi conferido com `php artisan route:list --path=api --except-vendor` (14 rotas).

### Documentação OpenAPI

Scramble gera contrato OpenAPI 3.1 a partir de rotas, middleware, Form Requests, tipos PHP, models e API Resources. Interface interativa fica em `/docs/api`; JSON fica em `/docs/api.json`. Ambos são públicos em ambiente local e produção; isso libera somente os documentos, não muda autenticação nem autorização das rotas API. OpenAPI usa cookie de sessão Sanctum, não bearer token. Operações protegidas herdam requisito de cookie; operações públicas declaram acesso anônimo.

Para browser SPA, primeiro solicite `GET /sanctum/csrf-cookie` fora da base `/api/v1`; envie cookies com credenciais e `X-XSRF-TOKEN` com valor URL-decoded do cookie `XSRF-TOKEN` nas mutações, inclusive cadastro e login. Configure CORS e Sanctum stateful domains conforme seção de sessão acima. IDs de rota são ULIDs internos, distintos dos identificadores YouTube Music.

O spec é gerado em runtime, sem cópia versionada. Após mudar rotas, Requests, Resources ou tipos de resposta, verifique seleção e schemas com:

```sh
php artisan scramble:analyze --fail-on-empty --fail-on-unknown
php artisan scramble:export --stdout --fail-on-unknown
```

`scramble:export --stdout` escreve JSON em stdout e diagnósticos em stderr; inspecione ambos, inclusive quando export retorna código diferente de zero.

| Método e caminho | Acesso | Uso |
|---|---|---|
| `POST /api/v1/register` | Sessão stateful + CSRF | Cria conta e inicia sessão; 201. |
| `POST /api/v1/login` | Sessão stateful + CSRF | Inicia sessão; credenciais inválidas retornam 401. |
| `POST /api/v1/logout` | Usuário autenticado + sessão/CSRF | Invalida sessão e token CSRF. |
| `GET /api/v1/me` | Usuário autenticado | Identidade atual. |
| `GET /api/v1/artists/search?q=...` | Autenticado | Candidatos externos, sem autoescolha. |
| `GET /api/v1/artists/{artist}` | Público | Detalhe local do artista por ULID. |
| `GET /api/v1/artists/{artist}/releases` | Autenticado | Catálogo local, sync status, stale/pending. |
| `POST /api/v1/artists/{artist}/sync` | Autenticado | Enfileira sync; 202. |
| `GET /api/v1/releases` | Público | Lista local de lançamentos. |
| `GET /api/v1/releases/{release}` | Público | Detalhe local por ULID. |
| `GET /api/v1/me/releases` | Usuário autenticado | Catálogo pessoal. |
| `GET /api/v1/me/releases/{release}` | Usuário autenticado | Entrada pessoal do proprietário atual. |
| `PUT /api/v1/me/releases/{release}` | Usuário autenticado | Substitui entrada pessoal. |
| `DELETE /api/v1/me/releases/{release}` | Usuário autenticado | Remove entrada pessoal, não o release compartilhado. |

Listas usam `page` (mínimo 1), `per_page` padrão **20**, máximo **100**; links `next` preservam filtros validados. Filtros/sort permitidos:

- `/releases` e `/me/releases`: `artist_id` (ULID), `type` (`album|ep|single|unknown`), `status` (`want_to_listen|listening|listened`), `rating` (1–5), `sort` (`title|release_year|created_at|rating|listened_at`), `direction` (`asc|desc`). Status/rating em `/releases` filtram catálogo pessoal do usuário autenticado; `/me/releases` sempre limita resultados ao dono atual.
- `/artists/{artist}/releases`: `type`, `sort` (`title|release_year|created_at`), `direction`. Paginação e filtros não fazem query remota por página: resultados vêm do catálogo local sincronizado.

Sort inválido e parâmetros fora dos limites retornam 422; sort não é expressão SQL livre. Exemplo página filtrada:

```sh
curl -i -b "$COOKIE_JAR" -G -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  --data-urlencode 'artist_id=01J...ULID...' \
  --data-urlencode 'type=album' --data-urlencode 'status=listened' \
  --data-urlencode 'rating=5' --data-urlencode 'sort=title' \
  --data-urlencode 'direction=asc' --data-urlencode 'page=1' \
  --data-urlencode 'per_page=20' "$API/api/v1/releases"
```

## Catálogo pessoal: PUT substitui, não faz PATCH

`PUT /api/v1/me/releases/{release}` usa ULID interno do release. `status` obrigatório; valores `want_to_listen`, `listening` ou `listened`. `rating` é inteiro nullable de 1–5; `listened_at` é data nullable `YYYY-MM-DD`, não futura em UTC; `notes` é string nullable até 5000 caracteres. Nota/data só são válidas com `status=listened`; status `listened` pode não ter nota nem data. `user_id` não é aceito para escolher proprietário.

Campos opcionais omitidos **são substituídos por null**, não preservados. Envie PUT completo em toda atualização; não trate como PATCH.

```sh
csrf
curl -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -H "X-XSRF-TOKEN: $XSRF_TOKEN" \
  -X PUT "$API/api/v1/me/releases/01J...RELEASE_ULID..." \
  --data '{"status":"listened","rating":5,"listened_at":"2026-10-06","notes":"Ouvi o álbum completo."}'
```

Para limpar os opcionais, faça novo PUT com somente `{"status":"listened"}`; resposta terá `rating`, `listened_at` e `notes` null. Para remover somente sua entrada:

```sh
csrf
curl -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H "Origin: $ORIGIN" -H 'Accept: application/json' \
  -H "X-XSRF-TOKEN: $XSRF_TOKEN" -X DELETE \
  "$API/api/v1/me/releases/01J...RELEASE_ULID..."
```

## Erros e limites

Respostas API usam JSON estável, inclusive com `APP_DEBUG=true`; exceções, caminho/linha, payload upstream e modelo interno não são mensagem pública. Códigos mais comuns:

| Status | Significado |
|---|---|
| 401 | Sessão ausente/credenciais inválidas. |
| 403 | Ação proibida. |
| 404 | Artista, release ou entrada pessoal não encontrada. |
| 405 | Método não permitido; mantém header `Allow`. |
| 419 | Origem/sessão stateful não confiável ou CSRF inválido/ausente. |
| 422 | Validação; inclui objeto `errors` por campo. |
| 429 | Limite excedido; respeite header `Retry-After`. |
| 500 | Falha inesperada com mensagem genérica. |
| 503 | Provider indisponível ou falha temporária. |

Limites atuais por minuto: cadastro/login **8** por email normalizado + IP; busca externa **20** por usuário + IP; sync **6** por usuário + IP. Busca em cache ainda está sujeita ao rate limit do endpoint. Em 429 aguarde `Retry-After` antes de repetir; não faça retry agressivo de validação/4xx.

## Testes, fixtures e validade externa

Testes direcionados por frente e suite completa:

```sh
php artisan test --compact tests/Feature/MusicDomainTest.php
php artisan test --compact tests/Unit/YouTubeMusicProviderTest.php
php artisan test --compact tests/Feature/ArtistSyncTest.php
php artisan test --compact tests/Feature/AuthenticationTest.php tests/Feature/MusicApiTest.php
php artisan test --compact
```

Sem Git confiável no checkout, não dependa de `pint --dirty`; passe arquivos PHP alterados explicitamente. Exemplo para serviços/comando/job Task 3:

```sh
vendor/bin/pint --format agent \
  app/Console/Commands/SyncArtistCatalog.php \
  app/Jobs/SyncArtistCatalogJob.php \
  app/Services/Music/ArtistCatalogService.php \
  app/Services/Music/ArtistSearchService.php \
  app/Services/Music/ArtistSyncService.php \
  tests/Feature/ArtistSyncTest.php
```

Suite usa provider/HTTP fakes e fixtures offline; **nenhuma chamada live a YouTube Music foi validada**. A integração usa endpoint interno não oficial, sujeito a mudança, bloqueio e limites do upstream; TLS fica habilitado, redirects desligados, tentativas/esperas limitadas e erros externos sanitizados. Ausência de live check não é prova de compatibilidade atual.

Referência local de Task 2: checkout upstream `sigma67/ytmusicapi` commit `4aeaf7d0aa48e3fb56eb229ec04593655acba091`; fonte inspecionada, não instalada/executada como dependência. Três respostas upstream copiadas byte a byte e hashes registrados em `docs/superpowers/plans/task-2-report.md`:

| Fixture local | Origem | SHA-256 |
|---|---|---|
| `tests/Fixtures/youtube-music/upstream-artist-single-column.json` | `2026_05_get_artist1.json` | `4287538775AB70B1B573754CBCCE1268F5FF6AE9DF48A87FA46B7BEB316847BF` |
| `tests/Fixtures/youtube-music/upstream-artist-two-column.json` | `2026_07_get_artist_two_column.json` | `426090FEC50ECDF00B2AB217BF903E509E668A39A481AB2B6AD58815B79B773C` |
| `tests/Fixtures/youtube-music/upstream-release.json` | `2026_05_get_album.json` | `BA3B9812F14B98FA7854432A31F702E00054BB779E61723F1292860DE667B7C` |

Fixtures de continuação e busca derivam da estrutura de resposta/código upstream inspecionados; não são alegados como capturas live. Testes offline verificam parsing, segunda página, catálogo vazio/inválido, retries e sincronização com fake provider, mas não substituem validação externa real.
