<?php

use Novius\LaravelTranslatable\Exceptions\TranslatableException;
use Workbench\App\Models\Product;
use Workbench\App\Models\ProductCommon;
use Workbench\App\Models\ProductCreatingCommon;
use Workbench\App\Models\ProductKeepingCommon;
use Workbench\App\Models\ProductLinkedByPosition;
use Workbench\App\Models\SoftDeletableProduct;
use Workbench\App\Models\SoftDeletableProductCommon;
use Workbench\App\Models\TranslatableModel;

function frenchProduct(string $title = 'Store'): Product
{
    return Product::factory()->create(['title' => $title]);
}

it('names the foreign key to the common record from the relationship', function () {
    expect((new Product)->commonRecordKeyName())->toBe('product_common_id');
});

it('gives a translation created with its parent id the common record of its parent', function () {
    $parent = frenchProduct();

    // As the back office does: the key of the parent, no common record
    $translation = Product::query()->create(['title' => 'Blind', 'locale' => 'en', 'locale_parent_id' => $parent->id]);

    expect($translation->product_common_id)->toBe($parent->product_common_id)
        ->and(ProductCommon::query()->count())->toBe(1);
});

it('keeps the common record when translating with translate()', function () {
    $parent = frenchProduct();

    $translation = $parent->translate('en', ['title' => 'Blind']);

    expect($translation->product_common_id)->toBe($parent->product_common_id)
        ->and($parent->fresh()->product_common_id)->toBe($parent->product_common_id);
});

it('refuses a translation with another common record than its group', function () {
    $parent = frenchProduct();
    $other = ProductCommon::query()->create();

    Product::query()->create(['title' => 'Blind', 'locale' => 'en', 'locale_parent_id' => $parent->id, 'product_common_id' => $other->id]);
})->throws(TranslatableException::class);

it('refuses to move a translation to another common record than its group', function () {
    $translation = frenchProduct()->translate('en', ['title' => 'Blind']);

    $translation->update(['product_common_id' => ProductCommon::query()->create()->id]);
})->throws(TranslatableException::class);

it('lets a model not translated change of common record, and deletes the former one', function () {
    $product = frenchProduct();
    $formerId = $product->product_common_id;
    $other = ProductCommon::query()->create();

    $product->update(['product_common_id' => $other->id]);

    expect($product->fresh()->product_common_id)->toBe($other->id)
        ->and(ProductCommon::query()->find($formerId))->toBeNull();
});

it('keeps the former common record of a model changing of common record when the model says so', function () {
    $product = ProductKeepingCommon::query()->create(['title' => 'Store', 'locale' => 'fr', 'product_common_id' => ProductCommon::query()->create()->id]);
    $formerId = $product->product_common_id;

    $product->update(['product_common_id' => ProductCommon::query()->create()->id]);

    expect(ProductCommon::query()->find($formerId))->not->toBeNull();
});

it('refuses to move a model to a group with another common record', function () {
    $parent = frenchProduct();

    frenchProduct('Awning')->update(['locale' => 'en', 'locale_parent_id' => $parent->id]);
})->throws(TranslatableException::class, 'All the translations of a resource must share the same common record.');

it('deletes the common record with its last translation only', function () {
    $parent = frenchProduct();
    $translation = $parent->translate('en', ['title' => 'Blind']);
    $commonId = $parent->product_common_id;

    $translation->delete();
    expect(ProductCommon::query()->find($commonId))->not->toBeNull();

    $parent->delete();
    expect(ProductCommon::query()->find($commonId))->toBeNull();
});

it('keeps the common record when the model says so', function () {
    $product = ProductKeepingCommon::query()->create(['title' => 'Store', 'locale' => 'fr', 'product_common_id' => ProductCommon::query()->create()->id]);

    $product->delete();

    expect(ProductCommon::query()->find($product->product_common_id))->not->toBeNull();
});

it('deletes the common record of a soft deletable model only when its last translation is force deleted', function () {
    $common = ProductCommon::query()->create();
    $parent = SoftDeletableProduct::query()->create(['title' => 'Store', 'locale' => 'fr', 'product_common_id' => $common->id]);
    $translation = $parent->translate('en', ['title' => 'Blind']);

    $parent->delete();
    $translation->delete();
    expect(ProductCommon::query()->find($common->id))->not->toBeNull();

    // A trashed translation still holds the common record: it can be restored
    $parent->forceDelete();
    expect(ProductCommon::query()->find($common->id))->not->toBeNull();

    $translation->forceDelete();
    expect(ProductCommon::query()->find($common->id))->toBeNull();
});

it('gives a translation of the common record by locale', function () {
    $parent = frenchProduct();
    $parent->translate('en', ['title' => 'Blind']);
    $common = ProductCommon::query()->find($parent->product_common_id);

    expect($common->translation('en')?->title)->toBe('Blind')
        ->and($common->translation('fr')?->title)->toBe('Store')
        ->and($common->translation('de'))->toBeNull();
});

it('labels the common record in the current locale, else the fallback locale, else any', function () {
    config(['app.fallback_locale' => 'fr']);
    $parent = frenchProduct();
    $parent->translate('en', ['title' => 'Blind']);
    $common = ProductCommon::query()->find($parent->product_common_id);

    app()->setLocale('en');
    expect($common->label())->toBe('Blind');

    app()->setLocale('de');
    expect($common->label())->toBe('Store');

    config(['app.fallback_locale' => 'it']);
    expect($common->label())->toBeIn(['Store', 'Blind'])
        ->and(ProductCommon::query()->create()->label())->toStartWith('#');
});

it('makes a translation sharing the common record of its parent with the factory', function () {
    $parent = frenchProduct();

    $translation = Product::factory()->translationOf($parent, 'en')->create(['title' => 'Blind']);

    expect($translation)
        ->locale->toBe('en')
        ->locale_parent_id->toBe($parent->id)
        ->product_common_id->toBe($parent->product_common_id)
        ->and(ProductCommon::query()->count())->toBe(1)
        ->and($parent->fresh()->translations)->toHaveCount(2);
});

it('refuses a model not translated with the common record of another group', function () {
    $product = frenchProduct();

    Product::query()->create(['title' => 'Awning', 'locale' => 'fr', 'product_common_id' => $product->product_common_id]);
})->throws(TranslatableException::class, 'A common record can only be shared by the translations of one resource.');

it('refuses to move a model not translated to the common record of another group', function () {
    $translated = frenchProduct();
    $translated->translate('en', ['title' => 'Blind']);

    frenchProduct('Awning')->update(['product_common_id' => $translated->product_common_id]);
})->throws(TranslatableException::class, 'A common record can only be shared by the translations of one resource.');

function productWithoutCommonRecord(): Product
{
    $product = frenchProduct();
    // As the data prior to the trait: a query, without the model events
    Product::query()->whereKey($product->id)->update(['product_common_id' => null]);

    return $product->refresh();
}

it('refuses a translation with a common record in a group without one, before saving it', function () {
    $parent = productWithoutCommonRecord();

    expect(fn () => Product::query()->create(['title' => 'Blind', 'locale' => 'en', 'locale_parent_id' => $parent->id, 'product_common_id' => ProductCommon::query()->create()->id]))
        ->toThrow(TranslatableException::class, 'All the translations of a resource must share the same common record.')
        ->and(Product::query()->count())->toBe(1)
        ->and($parent->fresh()->locale_parent_id)->toBeNull();
});

it('refuses to remove the common record of a translation only', function () {
    $translation = frenchProduct()->translate('en', ['title' => 'Blind']);

    $translation->update(['product_common_id' => null]);
})->throws(TranslatableException::class, 'All the translations of a resource must share the same common record.');

it('gives a new common record to a model created with a soft deleted parent', function () {
    $parent = SoftDeletableProduct::query()->create(['title' => 'Store', 'locale' => 'fr', 'product_common_id' => ProductCommon::query()->create()->id]);
    $parent->delete();

    // Translatable does not find the trashed parent: the model is an original, not sharing its common record
    $product = SoftDeletableProduct::query()->create(['title' => 'Blind', 'locale' => 'en', 'locale_parent_id' => $parent->id]);

    expect($product->locale_parent_id)->toBeNull()
        ->and($product->product_common_id)->not->toBeNull()
        ->and($product->product_common_id)->not->toBe($parent->product_common_id);
});

it('gives a translation made from a translation the common record of its group', function () {
    // HasCommonRecord used before Translatable, as Pint orders them: the group is resolved first all the same
    $parent = SoftDeletableProduct::query()->create(['title' => 'Store', 'locale' => 'fr', 'product_common_id' => ProductCommon::query()->create()->id]);
    $english = $parent->translate('en', ['title' => 'Blind']);

    $spanish = SoftDeletableProduct::query()->create(['title' => 'Persiana', 'locale' => 'es', 'locale_parent_id' => $english->id]);

    expect($spanish->locale_parent_id)->toBe($parent->id)
        ->and($spanish->product_common_id)->toBe($parent->product_common_id);
});

it('keeps the common record when translating a translation with translate()', function () {
    $parent = SoftDeletableProduct::query()->create(['title' => 'Store', 'locale' => 'fr', 'product_common_id' => ProductCommon::query()->create()->id]);

    $spanish = $parent->translate('en', ['title' => 'Blind'])->translate('es', ['title' => 'Persiana']);

    expect($spanish->locale_parent_id)->toBe($parent->id)
        ->and($spanish->product_common_id)->toBe($parent->product_common_id);
});

it('creates the common record of an original created without one, saving it', function () {
    $parent = Product::query()->create(['title' => 'Store', 'locale' => 'fr']);
    $translation = Product::query()->create(['title' => 'Blind', 'locale' => 'en', 'locale_parent_id' => $parent->id]);

    expect($parent->product_common_id)->not->toBeNull()
        ->and($parent->relationLoaded('commonRecord'))->toBeTrue()
        ->and($parent->commonRecord?->exists)->toBeTrue()
        ->and($translation->product_common_id)->toBe($parent->product_common_id)
        ->and(ProductCommon::query()->count())->toBe(1);
});

it('creates the common record of an original saved already', function () {
    $product = ProductCreatingCommon::query()->create(['title' => 'Store', 'locale' => 'fr']);

    expect($product->commonRecord?->position)->toBe(1)
        ->and(ProductCommon::query()->count())->toBe(1);
});

it('gives no common record to a translation of a parent without one', function () {
    $parent = productWithoutCommonRecord();
    $commonCount = ProductCommon::query()->count();

    $translation = Product::query()->create(['title' => 'Blind', 'locale' => 'en', 'locale_parent_id' => $parent->id]);

    expect($translation->product_common_id)->toBeNull()
        ->and(ProductCommon::query()->count())->toBe($commonCount);
});

it('refuses to delete a common record holding translations', function () {
    $product = frenchProduct();

    $product->commonRecord?->delete();
})->throws(TranslatableException::class, 'A common record cannot be deleted while it has translations.');

it('refuses to delete a common record holding soft deleted translations', function () {
    $product = SoftDeletableProduct::query()->create(['title' => 'Store', 'locale' => 'fr', 'product_common_id' => ProductCommon::query()->create()->id]);
    $product->delete();

    SoftDeletableProductCommon::query()->findOrFail($product->product_common_id)->delete();
})->throws(TranslatableException::class, 'A common record cannot be deleted while it has translations.');

it('deletes a common record without translations', function () {
    $common = ProductCommon::query()->create();

    expect($common->delete())->toBeTrue()
        ->and(ProductCommon::query()->count())->toBe(0);
});

it('deletes the common record by the owner key of the relationship', function () {
    $decoy = ProductCommon::query()->create();
    // Its position is the key of the other one
    $common = ProductCommon::query()->create(['position' => $decoy->id]);
    $product = ProductLinkedByPosition::query()->create(['title' => 'Store', 'locale' => 'fr', 'product_common_id' => $common->position]);

    $product->delete();

    expect(ProductCommon::query()->find($common->id))->toBeNull()
        ->and(ProductCommon::query()->find($decoy->id))->not->toBeNull();
});

it('labels the common record by its key when the attribute of its translation is empty', function () {
    app()->setLocale('fr');
    $product = frenchProduct('');

    expect($product->commonRecord?->label())->toBe('#'.$product->product_common_id);
});

it('makes a translation of a model without common record with the factory', function () {
    $parent = TranslatableModel::query()->create(['title' => 'Article', 'locale' => 'fr']);

    $translation = TranslatableModel::factory()->translationOf($parent, 'en')->create(['title' => 'Post']);

    expect($translation)
        ->locale->toBe('en')
        ->locale_parent_id->toBe($parent->id);
});

it('refuses to make with the factory a translation of a model not translatable', function () {
    Product::factory()->translationOf(new ProductCommon, 'en');
})->throws(InvalidArgumentException::class, 'must use the Translatable trait');
