# Laravel Translatable

[![Tests](https://github.com/novius/laravel-translatable/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/novius/laravel-translatable/actions/workflows/tests.yml)
[![Packagist Release](https://img.shields.io/packagist/v/novius/laravel-translatable.svg?maxAge=1800&style=flat-square)](https://packagist.org/packages/novius/laravel-translatable)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](http://www.gnu.org/licenses/agpl-3.0)


## Introduction

A package for making Laravel Eloquent models "translatable" using two fields: `locale` and `locale_parent_id`.

## Requirements

- Laravel: 10.x, 11.x, 12.x, 13.x
- PHP: 8.2 – 8.5

Only Laravel 12.x and 13.x are tested: every release of Laravel 10.x and 11.x has a known security vulnerability.

## Installation

You can install the package via composer:

```bash
composer require novius/laravel-translatable
```

You can publish lang files:

```bash
php artisan vendor:publish --tag=laravel-translatable-lang
```

## Usage

#### Migrations

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->translatable(); // Macro provided by the package
    $table->string('title');
    $table->text('text');
    $table->timestamps();
});
```

#### Eloquent Model Trait

```php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model;
use Novius\LaravelTranslatable\Traits\Translatable;

class Post extends Model {
    use Translatable;
    ...
}
```

This trait adds:
* A relation `translations` containing all translations of the model
* A relation `translationsWithDeleted` containing all translations of the model, including those in trash if your model use SoftDelete trait 
* A function `translate(string $locale, array $translateAttributes = [])` to translate a model in a new locale
* A function `getTranslation(string $locale, bool $withDeleted = false)` returning the translated model in specified locale or null if it doesn't exist. It reads the `translations` (or `translationsWithDeleted`) relation, loaded by the first call and reused by the following ones (see below).
* A scope `withLocale($locale)` on the query
* A scope `originals()` on the query, keeping only the original models: those not translated, and the root of each group of translations
* A function `isOriginal()` telling whether the model is an original

Once a model is translated, it holds its own key in the `locale_parent_id` column, like its translations: `translations`
then contains the whole group, the model included. A model not translated has a null `locale_parent_id` and no
`translations`.

A translation can also be created with the key of its parent in `locale_parent_id`
(`Post::create(['locale' => 'en', 'locale_parent_id' => $post->id, ...])`): the parent is then updated in the database,
not an instance of it already in memory. Call `$post->refresh()` before reading its `locale_parent_id` or `translations`.

`getTranslation()` loads the `translations` relation (`translationsWithDeleted` with `$withDeleted`) once, then reads it:
call it in a loop on models loaded with `->with('translations')` without any query. Translations created, updated or
deleted afterwards in the same request, by another instance, are not seen. Unset the relation to read them again:

```php
$post->unsetRelation('translations');
$post->unsetRelation('translationsWithDeleted');
```

`translate()` unsets them itself, and checks in the database that the locale is not already translated.

Deleting the original of a group of translations breaks the group: with the foreign key of the `translatable()` migration
macro (`on delete set null`), its translations lose their `locale_parent_id` and become originals not translated. A
soft deleted original stays the root of its group, but `originals()` no longer returns it, nor any model of its group.

```php
$post = new Post([
    'title' => 'Français',
]);
$post->save()

$post->translate('en', ['title' => 'English']);
$post->translate('es', ['title' => 'Español']);

// All translation including `fr`
$allTranslations = $post->translations;

$englishTranslation = $post->getTranslation('en');

// $italianTranslation is null
$italianTranslation = $post->getTranslation('it');

// The original posts only (here, the French one), one per group of translations
$originals = Post::query()->originals()->get();

```

You can override the `translatableConfig` method of the trait if you want to customize his behavior:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Novius\LaravelTranslatable\Support\TranslatableModelConfig;
use Novius\LaravelTranslatable\Traits\Translatable;

class Post extends Model {
    use Translatable;

    public function translatableConfig(): TranslatableModelConfig
    {
        return new TranslatableModelConfig(
            ['fr', 'en'], // Restricted translations to specified locals
            'locale', // The name of de `locale` column
            'locale_parent_id' // The name of de `locale_parent_id` column
        );
    }
}
```

You can override the `translateAttributes` method of the trait if you want to translate some attributes of the model before saving:

```php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model;
use Novius\LaravelTranslatable\Traits\Translatable;

class Post extends Model {
    use Translatable;

    protected function translateAttributes($parent): void
    {
        $this->some_attribut = $parent->some_attribut.' translated';
    }
    ...
}
```

## Testing

Run the linter, the static analysis and the tests with:

```bash
composer test
```

## Lint

Lint your code with Laravel Pint using:

```bash
composer lint
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Licence

This package is under [GNU Affero General Public License v3](http://www.gnu.org/licenses/agpl-3.0.html) or (at your option) any later version.
