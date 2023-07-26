# Laravel Translatable

[![Novius CI](https://github.com/novius/laravel-publishable/actions/workflows/main.yml/badge.svg?branch=main)](https://github.com/novius/laravel-translatable/actions/workflows/main.yml)
[![Packagist Release](https://img.shields.io/packagist/v/novius/laravel-translatable.svg?maxAge=1800&style=flat-square)](https://packagist.org/packages/novius/laravel-translatable)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](http://www.gnu.org/licenses/agpl-3.0)


## Introduction

A package for making Laravel Eloquent models "translatable" using 2 fields : locale and locale_parent_id.

## Requirements

* Laravel 8.0, 9.0 or 10.0

## Installation

You can install the package via composer:

```bash
composer require novius/laravel-translatable
```

```bash
php artisan vendor:publish --provider="Novius\Translatable\LaravelTranslatableServiceProvider" --tag=lang
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

This trait add :
* A relation `translations` containing all translations of the model
* A relation `translationsWithDeleted` containing all translations of the model, including those in trash if your model use SoftDelete trait 
* A function `translate(string $locale, array $translateAttributes = [])` to translate a model in a new locale
* A function `getTranslation(string $locale, bool $withDeleted = false)` returning the translated model in specified locale or null if it doesn't exist. 

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

### Testing

```bash
composer run test
```

## CS Fixer

Lint your code with Laravel Pint using:

```bash
composer run cs-fix
```

## Licence

This package is under [GNU Affero General Public License v3](http://www.gnu.org/licenses/agpl-3.0.html) or (at your option) any later version.
