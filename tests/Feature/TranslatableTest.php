<?php

use Illuminate\Support\Facades\DB;
use Novius\LaravelTranslatable\Exceptions\TranslatableException;
use Workbench\App\Models\CustomColumnsTranslatableModel;
use Workbench\App\Models\TranslatableModel;

function frenchModel(string $title = 'Français'): TranslatableModel
{
    return TranslatableModel::factory()->create([
        'locale' => 'fr',
        'title' => $title,
    ]);
}

it('translates a model', function () {
    $model = frenchModel();

    $translation = $model->translate('en', [
        'title' => 'English',
    ]);

    expect($model->fresh()->locale_parent_id)->toBe($model->id)
        ->and($translation->fresh())
        ->locale_parent_id->toBe($model->id)
        ->locale->toBe('en')
        ->title->toBe('English')
        ->and($translation->fresh()->translations)->toHaveCount(2)
        ->and($model->fresh()->translations)->toHaveCount(2);
});

it('cannot translate a model in its own locale', function () {
    frenchModel()->translate('fr');
})->throws(TranslatableException::class);

it('cannot translate a model twice in the same locale', function () {
    $model = frenchModel();
    $model->translate('en');

    $model->translate('en');
})->throws(TranslatableException::class);

it('has no translations when not translated', function () {
    $models = [
        frenchModel(),
        frenchModel('Français alt'),
        TranslatableModel::factory()->create([
            'locale' => 'en',
            'title' => 'English',
        ]),
    ];

    foreach ($models as $model) {
        expect($model->fresh()->translations)->toHaveCount(0);
    }
});

it('reloads the translations loaded before translating', function () {
    $model = frenchModel();
    expect($model->translations)->toHaveCount(0);

    $model->translate('en', [
        'title' => 'English',
    ]);

    expect($model->translations)->toHaveCount(2)
        ->and($model->getTranslation('en')?->title)->toBe('English');
});

it('considers originals the models not translated and the roots of their translations', function () {
    $alone = frenchModel('Seul');
    $root = frenchModel();
    $translation = $root->translate('en', [
        'title' => 'English',
    ]);

    expect(TranslatableModel::query()->originals()->pluck('id')->all())->toEqualCanonicalizing([$alone->id, $root->id])
        ->and($alone->isOriginal())->toBeTrue()
        ->and($root->isOriginal())->toBeTrue()
        ->and($root->fresh()->isOriginal())->toBeTrue()
        ->and($translation->isOriginal())->toBeFalse()
        ->and($translation->fresh()->isOriginal())->toBeFalse();
});

it('makes the parent the root of the group when a translation is created with its parent id', function () {
    $parent = frenchModel();

    $translation = TranslatableModel::query()->create([
        'locale' => 'en',
        'title' => 'English',
        'locale_parent_id' => $parent->id,
    ]);

    // The parent is updated in the database, not the instance already in memory
    expect($parent->locale_parent_id)->toBeNull()
        ->and($parent->fresh()->locale_parent_id)->toBe($parent->id)
        ->and($translation->locale_parent_id)->toBe($parent->id)
        ->and(TranslatableModel::query()->originals()->pluck('id')->all())->toBe([$parent->id])
        ->and($parent->fresh()->translations)->toHaveCount(2);
});

it('rolls back the transaction of a failed translation', function () {
    $model = frenchModel();

    // The creating event throws: the locale is not available
    expect(fn () => $model->translate('de'))->toThrow(TranslatableException::class)
        ->and(DB::transactionLevel())->toBe(0)
        ->and(TranslatableModel::query()->count())->toBe(1)
        ->and($model->fresh()->locale_parent_id)->toBeNull();
});

it('qualifies the column of the with locale scope', function () {
    $model = frenchModel();
    $translation = $model->translate('en', [
        'title' => 'English',
    ]);

    // Both tables of the join have a locale column
    $ids = TranslatableModel::query()
        ->join('translatable_models as roots', 'roots.id', '=', 'translatable_models.locale_parent_id')
        ->withLocale('en')
        ->pluck('translatable_models.id')
        ->all();

    expect($ids)->toBe([$translation->id]);
});

it('uses the configured columns', function () {
    $model = CustomColumnsTranslatableModel::query()->create([
        'lang' => 'fr',
        'title' => 'Français',
    ]);
    $translation = $model->translate('en', [
        'title' => 'English',
    ]);

    expect($model->fresh()->lang_parent_id)->toBe($model->id)
        ->and(CustomColumnsTranslatableModel::query()->withLocale('en')->pluck('id')->all())->toBe([$translation->id])
        ->and(CustomColumnsTranslatableModel::query()->originals()->pluck('id')->all())->toBe([$model->id])
        ->and($model->isOriginal())->toBeTrue()
        ->and($translation->isOriginal())->toBeFalse();
});
