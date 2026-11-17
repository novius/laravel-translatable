# Changelog

All notable changes to `novius/laravel-translatable` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/novius/laravel-translatable/compare/2.1.1...HEAD)

## [2.1.1](https://github.com/novius/laravel-translatable/compare/2.1.0...2.1.1) - 2026-11-17

### Fixed

- The examples of the README and of the Boost skill name the foreign key of the `commonRecord()` relation
  (`product_common_id`): guessed from the name of the method, it would be `common_record_id`.

## [2.1.0](https://github.com/novius/laravel-translatable/compare/2.0.2...2.1.0) - 2026-11-17

### Added

- The `originals()` scope and the `isOriginal()` method on the `Translatable` trait: the original models are those not
  translated and the root of each group of translations, which holds its own key in the locale parent id column once
  translated.
- The lang files are published with the `lang` and `laravel-translatable-lang` tags: the `--tag=lang` documented in the
  README published nothing.
- A Laravel Boost skill, `laravel-translatable-development`, to help agents use the package in Laravel applications.
- Common record and translations: the `HasCommonRecord` trait, on a translatable model whose data shared by all the
  locales is held by a common record (`Product`), and the `IsCommonRecord` trait, on that common record
  (`ProductCommon`). A translation created with its parent's key and no common record gets the one of its parent; all
  the translations of a group share the same common record (or none), and a common record belongs to one group only,
  otherwise saving throws a `TranslatableException`; an original created without common record gets the one returned
  by the abstract `createCommonRecord()`; the common record is deleted when no model holds it any more (force
  deleted, for a soft deletable model), unless `deletesCommonRecordWithLastTranslation()` returns `false`, and cannot be
  deleted while it holds translations. The common record gives `translation($locale)` and `label()`, which reads the
  attribute of the translations named by the abstract `commonRecordLabelAttribute()`. The `MakesTranslations`
  factory trait makes a translation of a model, sharing its common record with `HasCommonRecord`
  (`translationOf($parent, $locale)`).

### Changed

- `getTranslation()` reads the `translations` relation (`translationsWithDeleted` with `$withDeleted`), loaded by its first
  call, instead of querying the database on each call: one query per model whatever the number of calls, none when the
  relation is eager loaded. It does not throw a lazy loading violation when lazy loading is prevented.
  **Upgrade note**: translations created, updated or deleted afterwards in the same request, by another instance, are
  no longer seen. Call `unsetRelation('translations')` and `unsetRelation('translationsWithDeleted')` to read them again.

### Fixed

- `translate()` checks in the database, soft deleted translations included, that the locale is not already translated:
  a soft deleted translation was only detected by the `creating` event, after the attributes were translated.
  
- A model created as a translation unloads the `translations` and `translationsWithDeleted` relations copied by
  `replicate()` from the model translated: they showed the group without it.
  
- `translate()` unloads the `translations` and `translationsWithDeleted` relations of the model and of its new
  translation: loaded before, they no longer showed the group of translations.
  
- `translate()` rolls back its transaction on any exception, including those thrown by the `creating` event (a locale
  not available, for example): the transaction was left open.
  
- The `withLocale()` scope uses the locale column of `translatableConfig()`, qualified with the table name: it used an
  unqualified `locale` column.
  
- The deprecation messages of `getLocaleColumn()` and `getLocaleParentIdColumn()` announce their removal in 3.0: they
  announced it in 2.0, where they were deprecated.
  

## [2.0.2](https://github.com/novius/laravel-translatable/compare/2.0.1...2.0.2) - 2026-05-11

### Added

- Laravel 13 support.

## [2.0.1](https://github.com/novius/laravel-translatable/compare/2.0.0...2.0.1) - 2026-02-20

### Added

- Laravel 10 support, dropped by 2.0.0, is restored.

## [2.0.0](https://github.com/novius/laravel-translatable/compare/1.1.0...2.0.0) - 2026-02-18

### Added

- The `translatableConfig()` method on the `Translatable` trait, returning a `TranslatableModelConfig`: the available
  locales of the model and the names of its `locale` and `locale_parent_id` columns. Override it to customize them.
- Creating a model in a locale that is not available throws a `TranslatableException`. By default, the available
  locales are those installed with `laravel-lang/locales`, now required by the package.

### Changed

- **Upgrade note**: the trait no longer reads the names of the columns from the `LOCALE` and `LOCALE_PARENT_ID`
  constants of the model. Override `translatableConfig()` instead.

### Deprecated

- `getLocaleColumn()` and `getLocaleParentIdColumn()`: use `translatableConfig()->locale_column` and
  `translatableConfig()->locale_parent_id_column`.

### Removed

- The `getQualifiedLocaleColumn()` and `getQualifiedLocaleParentIdColumn()` methods.
- Laravel 10 support (restored by 2.0.1).

## [1.1.0](https://github.com/novius/laravel-translatable/compare/1.0.0...1.1.0) - 2025-03-07

### Added

- Laravel 12 support.

### Changed

- The package requires `laravel/framework` instead of `illuminate/database` and `illuminate/support`.
- `translations()` and `translationsWithDeleted()` declare their `HasMany` return type.

### Fixed

- The `dropTranslatable()` macro gets the name of the foreign key from the table name with `getTable()`, the
  `prefix` and `table` properties of the blueprint being no longer available with Laravel 12.

## [1.0.0](https://github.com/novius/laravel-translatable/compare/0.0.5...1.0.0) - 2024-07-10

### Added

- Laravel 11 support.

### Removed

- Support for PHP < 8.2, Laravel 8 and Laravel 9.

## [0.0.5](https://github.com/novius/laravel-translatable/compare/0.0.4...0.0.5) - 2023-09-15

### Fixed

- `getTranslation()` on a model not translated returns the model itself when asked for its own locale.

## [0.0.4](https://github.com/novius/laravel-translatable/compare/0.0.3...0.0.4) - 2023-09-08

### Added

- The `withLocale()` scope.

## [0.0.3](https://github.com/novius/laravel-translatable/compare/0.0.2...0.0.3) - 2023-07-26

### Added

- The `translateAttributes($parent)` method on the trait, called on a new translation before it is saved: override it
  to translate some attributes of the model.

## [0.0.2](https://github.com/novius/laravel-translatable/compare/0.0.1...0.0.2) - 2023-07-24

### Fixed

- The `dropTranslatable()` macro drops the foreign key of the locale parent id column before the columns.

## [0.0.1](https://github.com/novius/laravel-translatable/releases/tag/0.0.1) - 2023-07-17

### Added

- Initial release: the `Translatable` trait, with `translate()`, `getTranslation()`, the `translations` and
  `translationsWithDeleted` relations, and support for soft deletes; the `translatable()` and `dropTranslatable()`
  Blueprint macros; the `TranslatableException`.
- Creating a model with the key of another one in its locale parent id column makes it a translation of that model.
