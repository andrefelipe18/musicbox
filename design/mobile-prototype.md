# MusicBox Mobile — protótipo

Protótipo navegável do app Flutter (`mobile/`), feito no Claude Design:

**https://claude.ai/artifact/DC8DWvDxfyVm8M55Z5hNrB**

Canvas "MusicBox — App Mobile": 7 telas de 390×844, todas interativas, na ordem do fluxo. Os tokens visuais (cores, tipografia, raios) são os de [`DESIGN.md`](DESIGN.md): fundo `#08070c`, violeta `#7c3aed` como única cor preenchida, Instrument Sans + JetBrains Mono. Este arquivo cobre só o que é específico do app: telas, navegação e ligação com a API (`backend/`, rotas `/api/v1`).

## Fluxo

```
1 Entrar ──► 2 Início ──┬─► 3 Buscar ──► 4 Artista ──► 5 Release ──► 6 Registrar
                        │                   │              ▲  │            │
                        │                   └─ "+" ────────┼──┴─ editar ───┤
                        └─► 7 Biblioteca ───────────────────┘   salvar ────┘ (volta p/ 7)
```

Barra inferior (telas 2, 3, 4, 5, 7): **Início · Buscar · Biblioteca**. Em 4 e 5 a aba "Buscar" fica ativa; 1 e 6 não têm barra (6 é modal, fecha com ✕).

## Telas

| # | Tela | Arquivo no canvas | O que mostra |
|---|------|-------------------|--------------|
| 1 | Entrar | `Main.dc.html` | Abas Entrar / Criar conta, campos e-mail e senha, botão Entrar |
| 2 | Início | `Home.dc.html` | Data, botão Registrar, "Ouvindo agora", "Novos no catálogo" (carrossel), "Recentes na sua biblioteca" |
| 3 | Buscar artista | `Search.dc.html` | Campo de busca, lista de candidatos, aviso de escolha manual, "Já no catálogo" |
| 4 | Artista | `Artist.dc.html` | Capa, nome, badge de sync, filtro Todos/Álbuns/EPs/Singles, lista de releases com ação por linha |
| 5 | Release | `Release.dc.html` | Capa, título, artista, "Meu registro" (status, data, nota, texto), Editar / Remover |
| 6 | Registrar | `Log.dc.html` | Status (3 opções), nota 1–5, data, notas (≤ 5000), Salvar / Cancelar |
| 7 | Biblioteca | `Library.dc.html` | Filtro por status, contagem, ordenação, lista com nota em estrelas e data |

## Tela → API

| Tela | Dados | Endpoint |
|------|-------|----------|
| 1 Entrar | login | `POST /login` |
| 1 Criar conta | cadastro | `POST /register` (exige `name`, `email`, `password` + confirmação) |
| 2 Novos no catálogo | releases mais recentes | `GET /releases` (`sort=created_at`, `direction=desc`) |
| 2 Ouvindo agora, Recentes | registros do usuário | `GET /me/releases` |
| 3 Candidatos | busca no catálogo externo | `GET /artists/search?q=` (mín. 2 caracteres) |
| 4 Cabeçalho + badge | artista e estado de sync | `GET /artists/{artist}` (`sync_status`, `catalog_synced_at`) |
| 4 Lista + filtros | releases do artista | `GET /artists/{artist}/releases` (`type`, `sort`, `direction`, paginação) |
| 4 Atualização do catálogo | enfileirar sync | `POST /artists/{artist}/sync` |
| 5 Release | detalhe e meu registro | `GET /releases/{release}`, `GET /me/releases/{release}` |
| 5 Remover registro | apagar | `DELETE /me/releases/{release}` |
| 6 Salvar | criar/substituir registro | `PUT /me/releases/{release}` |
| 7 Biblioteca | lista, filtro, ordenação | `GET /me/releases` |

## Regras que as telas já respeitam

- **Status** (tela 6, filtro da 7): `want_to_listen` "Quero ouvir", `listening` "Ouvindo", `listened` "Ouvi" — igual ao enum `UserReleaseStatus`.
- **Nota e data só com status "Ouvi"**: na tela 6 esses campos só aparecem quando `listened`; o backend recusa `rating`/`listened_at` em outros status.
- **Nota** 1–5, opcional (tocar na mesma estrela limpa). **Data** não pode ser futura. **Notas** até 5000 caracteres.
- **Tipos de release** (filtro da tela 4): Álbuns, EPs e Singles ↔ `album`, `ep`, `single` (`unknown` não tem filtro).
- **Sync do artista** (badge da tela 4): estados do enum `SyncStatus` (`idle`, `queued`, `running`, `failed`). O protótipo só desenha `queued` (âmbar, "Mostrando o catálogo anterior. Uma atualização está na fila."); `running` e `failed` ainda não têm design (no `DESIGN.md`, falha é rosa, só contorno).
- **Busca nunca escolhe por você**: a lista da tela 3 é só candidatos; o usuário escolhe manualmente.
- **Imagens**: capas são placeholders (quadrado colorido com círculo). Artista sem imagem usa o rótulo `NO_ART`.

## Lacunas entre protótipo e backend

Pontos a decidir antes de implementar:

1. **"Criar conta" (tela 1)** é só uma aba: não há formulário. O backend pede nome e confirmação de senha.
2. **"Já no catálogo" (tela 3)** lista artistas já sincronizados, mas não existe endpoint de listagem de artistas (só busca, detalhe e releases).
3. **Estados da tela 4** para cada release (adicionar `+`, "Ouvindo", nota) dependem de o `GET /artists/{artist}/releases` trazer o registro do usuário junto. Confirmar no `ReleaseResource`.
4. **Filtros de `/me/releases`** (status, ordenar por data): confirmar se `ListUserReleasesController` aceita os mesmos parâmetros de `/releases`.
5. **Sem tela** para sair (`POST /logout`) nem para o perfil (`GET /me`).
6. **Estados vazios, carregando e de erro** não estão desenhados em nenhuma tela.
7. **Textos em português** no protótipo; o app Flutter ainda não tem i18n definido.
