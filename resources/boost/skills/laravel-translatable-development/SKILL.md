---
name: laravel-translatable-development
description: >
  Make Eloquent models translatable with novius/laravel-translatable: one row per locale, grouped by a locale parent id.
  Use when adding translations to a model, translating a model, querying models by locale, or listing the originals.
license: MIT
metadata:
  author: Novius
---

# Laravel Translatable

Use this skill when a Laravel application needs translatable Eloquent models with `novius/laravel-translatable`.

## Primary Goal

- store each locale of a model in its own row, grouped by a locale parent id, through the `Translatable` trait

## Workflow

### 1. Add the columns

Use the Blueprint macro in the model's migration. It adds a `locale` string column, a nullable `locale_parent_id`
column, a unique index on both, and a foreign key on the table's `id` (`on delete set null`).

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->translatable(); // or ->translatable('lang', 'lang_parent_id', 'id')
});

// Down migration of an existing table
$table->dropTranslatable();
```

### 2. Use the trait

```php
use Novius\LaravelTranslatable\Traits\Translatable;

class Post extends Model
{
    use Translatable;
}
```

Override `translatableConfig()` to change the column names or restrict the locales (by default, the locales
installed with `laravel-lang/locales`):

```php
use Novius\LaravelTranslatable\Support\TranslatableModelConfig;

public function translatableConfig(): TranslatableModelConfig
{
    return new TranslatableModelConfig(
        available_locales: ['fr', 'en'],
        locale_column: 'lang',
        locale_parent_id_column: 'lang_parent_id',
    );
}
```

Override `translateAttributes($parent)` to change attributes of a new translation, such as a unique slug.

### 3. Translate and query

```php
$post = Post::create(['locale' => 'fr', 'title' => 'Bonjour']);

$english = $post->translate('en', ['title' => 'Hello']); // throws TranslatableException if it already exists
$post->getTranslation('en');           // ?Post
$post->getTranslation('en', true);     // includes soft deleted translations
$post->translations;                   // the whole group, $post included, once translated

Post::query()->withLocale('en')->get();
Post::query()->originals()->get();     // one model per group: the not translated ones and the roots
$post->isOriginal();
```

## Rules

- Once translated, the original holds its own key in `locale_parent_id`, like its translations: never use
  `whereNull('locale_parent_id')` to list originals, use the `originals()` scope.
- A model not translated has a null `locale_parent_id` and no `translations`.
- A translation created with `locale_parent_id` set to its parent's key updates the parent in the database only:
  call `$parent->refresh()` before reading its `locale_parent_id` or `translations`.
- Deleting the original breaks the group: its translations lose their `locale_parent_id`.
- `translate()` and creation throw `TranslatableException` when the locale is not available or already translated.

## Examples

- Add a French/English blog: add `$table->translatable()` to the posts migration, use the trait, create the French
  post, then `$post->translate('en', [...])`.
- List the posts of the current locale: `Post::query()->withLocale(app()->getLocale())->get()`.
- Build a back office list with one line per group: `Post::query()->originals()->with('translations')->get()`.

## Anti-patterns

- Hard-coding the `locale` and `locale_parent_id` column names when the model overrides `translatableConfig()`.
- Reading `translations` on a parent instance loaded before a translation was created with `create()`.
- Creating translations by copying rows by hand instead of `translate()`, which checks the locale and the group.
