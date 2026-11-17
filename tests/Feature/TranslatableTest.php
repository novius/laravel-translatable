<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Novius\LaravelTranslatable\Exceptions\TranslatableException;
use Workbench\App\Models\CustomColumnsTranslatableModel;
use Workbench\App\Models\SoftDeletableTranslatableModel;
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

it('reads the translations of a model with one query, whatever the number of calls', function () {
    frenchModel()->translate('en', [
        'title' => 'English',
    ]);
    $model = TranslatableModel::query()->withLocale('fr')->firstOrFail();

    DB::enableQueryLog();
    $titles = [
        $model->getTranslation('en')?->title,
        $model->getTranslation('fr')?->title,
        $model->getTranslation('en')?->title,
    ];

    expect($titles)->toBe(['English', 'Français', 'English'])
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('reads the eager loaded translations without any query', function () {
    frenchModel('Un')->translate('en', ['title' => 'One']);
    frenchModel('Deux')->translate('en', ['title' => 'Two']);
    $models = TranslatableModel::query()->withLocale('fr')->with('translations')->orderBy('id')->get();

    DB::enableQueryLog();
    $titles = $models->map(fn (TranslatableModel $model) => $model->getTranslation('en')?->title)->all();

    expect($titles)->toBe(['One', 'Two'])
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('reads the translations of models without triggering a lazy loading violation', function () {
    frenchModel('Un')->translate('en', ['title' => 'One']);
    frenchModel('Deux')->translate('en', ['title' => 'Two']);

    Model::preventLazyLoading();
    try {
        $titles = TranslatableModel::query()->withLocale('fr')->orderBy('id')->get()
            ->map(fn (TranslatableModel $model) => $model->getTranslation('en')?->title)
            ->all();
    } finally {
        Model::preventLazyLoading(false);
    }

    expect($titles)->toBe(['One', 'Two']);
});

it('keeps the translations read until the relation is unloaded', function () {
    $model = frenchModel();
    $model->translate('en', ['title' => 'English']);
    expect($model->getTranslation('en')?->title)->toBe('English');

    TranslatableModel::query()->withLocale('en')->update(['title' => 'Updated']);

    expect($model->getTranslation('en')?->title)->toBe('English');

    $model->unsetRelation('translations');

    expect($model->getTranslation('en')?->title)->toBe('Updated');
});

it('reads the translations of a translation created with the id of its parent', function () {
    $parent = frenchModel();

    $translation = TranslatableModel::query()->create([
        'locale' => 'en',
        'title' => 'English',
        'locale_parent_id' => $parent->id,
    ]);

    expect($translation->getTranslation('fr')?->title)->toBe('Français')
        ->and($translation->getTranslation('en', true)?->title)->toBe('English')
        ->and($translation->translations)->toHaveCount(2);
});

it('unloads the translations copied from the model replicated to create a translation', function () {
    $parent = frenchModel();
    $parent->load(['translations', 'translationsWithDeleted']);

    $translation = $parent->replicate()->fill([
        'locale' => 'en',
        'title' => 'English',
        'locale_parent_id' => $parent->id,
    ]);
    $translation->save();

    expect($translation->translations)->toHaveCount(2)
        ->and($translation->translationsWithDeleted)->toHaveCount(2);
});

it('reads the soft deleted translations only when asked', function () {
    $model = SoftDeletableTranslatableModel::query()->create(['locale' => 'fr', 'title' => 'Français']);
    $model->translate('en', ['title' => 'English'])->delete();

    expect($model->getTranslation('en'))->toBeNull()
        ->and($model->getTranslation('en', true)?->title)->toBe('English');
});

it('cannot translate a model in a locale whose translation is soft deleted', function () {
    $model = SoftDeletableTranslatableModel::query()->create(['locale' => 'fr', 'title' => 'Français']);
    $model->translate('en', ['title' => 'English'])->delete();

    expect(fn () => $model->translate('en'))
        ->toThrow(TranslatableException::class, trans('translatable::messages.translation_deleted_exist'));
});

it('cannot translate a model in a locale translated since its translations were read', function () {
    $model = frenchModel();
    $model->translate('en');
    TranslatableModel::query()->withLocale('en')->delete();
    // Loads the translations of the group: the French model only
    $model->getTranslation('fr');

    // Another instance translates the model again: the translations read by $model no longer show the group
    TranslatableModel::query()->findOrFail($model->id)->translate('en');

    expect(fn () => $model->translate('en'))
        ->toThrow(TranslatableException::class, trans('translatable::messages.already_translated'));
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
