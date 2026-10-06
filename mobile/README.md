# Musicbox Mobile

App Flutter do Musicbox, com Clean Architecture e BLoC. Consome a API do [`../backend`](../backend).

Começou a partir do template [`base_clean_arch_bloc`](https://github.com/tecrodrigocastro/base_clean_arch_bloc). As convenções de arquitetura, nomes e SOLID estão em [`.claude/rules/`](.claude/rules/) e no [`CLAUDE.md`](CLAUDE.md). A feature `auth` é a referência para novas features.

## Como rodar

```bash
cd mobile
fvm flutter pub get
fvm flutter run
```

## Testes

```bash
fvm flutter test
```

## Nova feature

Gerada com [Mason](https://pub.dev/packages/mason_cli) a partir de `bricks/feature/`:

```bash
dart pub global activate mason_cli
mason get
mason make feature --feature_name product --action_name create --fields "id:String,name:String" -o .
```

A ligação no `lib/src/core/DI/dependency_injector.dart` continua manual (seguir o bloco do `auth`).
