<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Novius\LaravelTranslatable\LaravelTranslatableServiceProvider;

it('publishes the lang files with the lang tags', function (string $tag) {
    expect(ServiceProvider::pathsToPublish(LaravelTranslatableServiceProvider::class, $tag))
        ->toHaveCount(1)
        ->toContain(lang_path('vendor/translatable'));
})->with(['lang', 'laravel-translatable-lang']);

it('loads the translations of the package', function () {
    expect(trans('translatable::messages.already_translated'))
        ->not->toBe('translatable::messages.already_translated');
});

it('adds the translatable columns with the blueprint macro', function () {
    expect(Schema::hasColumns('translatable_models', ['locale', 'locale_parent_id']))->toBeTrue()
        ->and(Schema::hasColumns('custom_columns_translatable_models', ['lang', 'lang_parent_id']))->toBeTrue();
});
