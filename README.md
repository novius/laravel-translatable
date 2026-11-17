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

#### Common record and translations

When a model has much data shared by all the locales and a few translated texts, one row per locale duplicates the
shared data. Split it in two models: the translatable model keeps the name of the content and holds the texts of each
locale (`Product`, one row per locale), a common record holds the rest (`ProductCommon`, one row for all the locales).

```php
Schema::create('product_commons', function (Blueprint $table) {
    $table->id();
    $table->string('image')->nullable();
    $table->unsignedInteger('position')->nullable();
    $table->timestamps();
});

Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_common_id')->constrained();
    $table->string('title');
    $table->string('slug');
    $table->timestamps();
    $table->translatable();
});
```

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Novius\LaravelTranslatable\Traits\HasCommonRecord;
use Novius\LaravelTranslatable\Traits\IsCommonRecord;
use Novius\LaravelTranslatable\Traits\Translatable;

class Product extends Model
{
    /** @use HasCommonRecord<ProductCommon> */
    use HasCommonRecord;
    use Translatable;

    /**
     * @return BelongsTo<ProductCommon, $this>
     */
    public function commonRecord(): BelongsTo
    {
        // Name the foreign key: guessed from the name of the method, it would be common_record_id
        return $this->belongsTo(ProductCommon::class, 'product_common_id');
    }

    // The common record of an original created without one: saved by the package if it is not yet
    protected function createCommonRecord(): Model
    {
        return new ProductCommon;
    }
}

class ProductCommon extends Model
{
    /** @use IsCommonRecord<Product> */
    use IsCommonRecord;

    /**
     * @return HasMany<Product, $this>
     */

    public function translations(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // The attribute of the translations that labels the common record
    public function commonRecordLabelAttribute(): string
    {
        return 'title';
    }
}
```

`HasCommonRecord`, on the translatable model:

* A translation created with the key of its parent in `locale_parent_id`, and without common record, gets the common
  record of its parent (as the back office creates a translation). `translate()` keeps it too.
* An original created without common record gets the one returned by `createCommonRecord()` (abstract), saved by the
  package if it is not yet: fill there the required columns of the common table. A model created with a parent not
  found, or soft deleted, is not a translation: it gets a new common record too. A form saving the common record after
  the model (a Filament `relationship('commonRecord')`, for instance) updates this one.
* All the translations of a group share the same common record, and a common record belongs to one group only: a
  model created or updated otherwise throws a `TranslatableException`, before being saved. A group without common
  record (data prior to the trait) is accepted: its new translations get none. To change the
  common record of a translated group, update all its models in one query, which fires no model event (the former
  common record is not deleted):
  `Product::query()->where('locale_parent_id', $product->locale_parent_id)->update(['product_common_id' => $id])`.
* The common record is deleted when no model holds it any more: with its last translation, or when the last model
  holding it changes of common record. For a soft deletable model, only when the last translation is force deleted: a
  trashed translation can be restored with its common record. Override `deletesCommonRecordWithLastTranslation()` to
  return `false` to keep the common record. Models deleted with a query (`Product::query()->…->delete()`, a Filament
  `DeleteBulkAction` with `fetchSelectedRecords(false)`, a database cascade) fire no model event: their common record
  is not deleted.
* `commonRecordKeyName()` gives the name of the foreign key, read from the `commonRecord()` relationship.

`IsCommonRecord`, on the common record:

* `translation(string $locale)` gives the translation in the locale, read in the `translations` relation.
* `label()` gives the attribute named by `commonRecordLabelAttribute()` (abstract: the package does not create it) of
  the translation in the current locale, else in the fallback locale of the application, else of any translation, else
  `#<key>`.
* A common record holding translations, soft deleted included, cannot be deleted: it throws a `TranslatableException`.
  Delete its translations: the last one deletes it.

#### Factories

In the factory of a translatable model, the `MakesTranslations` trait makes a translation of a model, in its group,
and sharing its common record with `HasCommonRecord`:

```php
use Novius\LaravelTranslatable\Factories\MakesTranslations;

class ProductFactory extends Factory
{
    use MakesTranslations;

    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'locale' => 'fr',
        ];
    }
}

$english = Product::factory()->translationOf($french, 'en')->create(['title' => 'Blind']);
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
