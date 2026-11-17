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

### 4. Common record and translations

When a model has much data shared by all the locales and a few translated texts, keep the translatable model for the
texts of each locale (`Product`) and move the shared data and relations to a common record (`ProductCommon`, table
`product_commons`, foreign key `product_common_id` on the translatable table):

```php
use Novius\LaravelTranslatable\Traits\HasCommonRecord;
use Novius\LaravelTranslatable\Traits\IsCommonRecord;

class Product extends Model
{
    /** @use HasCommonRecord<ProductCommon> */
    use HasCommonRecord;
    use Translatable;

    public function commonRecord(): BelongsTo
    {
        // Name the foreign key: guessed from the name of the method, it would be common_record_id
        return $this->belongsTo(ProductCommon::class, 'product_common_id');
    }

    protected function createCommonRecord(): Model
    {
        return new ProductCommon; // required: the common record of a new original, saved by the package
    }
}

class ProductCommon extends Model
{
    /** @use IsCommonRecord<Product> */
    use IsCommonRecord;

    public function translations(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function commonRecordLabelAttribute(): string
    {
        return 'title'; // required: the attribute of the translations that labels the common record
    }
}

$common->translation('en');  // ?Product
$common->label();            // that attribute in the current locale, else the fallback locale, else any
Product::factory()->translationOf($french, 'en')->create(); // with the MakesTranslations factory trait
```

## Rules

- Once translated, the original holds its own key in `locale_parent_id`, like its translations: never use
  `whereNull('locale_parent_id')` to list originals, use the `originals()` scope.
- A model not translated has a null `locale_parent_id` and no `translations`.
- A translation created with `locale_parent_id` set to its parent's key updates the parent in the database only:
  call `$parent->refresh()` before reading its `locale_parent_id` or `translations`.
- Deleting the original breaks the group: its translations lose their `locale_parent_id`.
- `translate()` and creation throw `TranslatableException` when the locale is not available or already translated.
- `getTranslation()` loads the `translations` (or `translationsWithDeleted`) relation once and reuses it: translations
  created, updated or deleted afterwards in the same request are not seen. Call `$post->unsetRelation('translations')`
  (and `'translationsWithDeleted'`) to read them again.
- With `HasCommonRecord`, a translation created with `locale_parent_id` and no foreign key gets the common record of
  its parent; all the translations of a group share it and a common record belongs to one group only,
  otherwise saving throws `TranslatableException`; the common record is deleted when no model holds it any more (force
  deleted, for a soft deletable model) unless `deletesCommonRecordWithLastTranslation()` returns `false`.
- An original created without foreign key gets the common record returned by `createCommonRecord()`, saved if it is
  not yet: fill the required columns of the common table there. Factory definitions need no foreign key.
- A common record holding translations, soft deleted included, cannot be deleted: delete its translations, the last
  one deletes it. Deleting translations with a query (or a Filament `DeleteBulkAction` with
  `fetchSelectedRecords(false)`) fires no event and leaves the common record.
- To change the common record of a translated group, update all its models in one query
  (`->where('locale_parent_id', $groupId)->update([...])`): updating the foreign key of one translation is refused.
- The translatable model keeps the name of the content (`Product`): the common record takes the `Common` suffix.

## Examples

- Add a French/English blog: add `$table->translatable()` to the posts migration, use the trait, create the French
  post, then `$post->translate('en', [...])`.
- List the posts of the current locale: `Post::query()->withLocale(app()->getLocale())->get()`.
- Link each post of a list to its translation without a query per post:
  `Post::query()->withLocale('fr')->with('translations')->get()`, then `$post->getTranslation('en')` in the loop.
- Build a back office list with one line per group: `Post::query()->originals()->with('translations')->get()`.

## Anti-patterns

- Hard-coding the `locale` and `locale_parent_id` column names when the model overrides `translatableConfig()`.
- Reading `translations` on a parent instance loaded before a translation was created with `create()`.
- Calling `getTranslation()` after changing translations in the same request without unsetting the relation first.
- Creating translations by copying rows by hand instead of `translate()`, which checks the locale and the group.
- Duplicating data shared by all the locales on each translation instead of moving it to a common record.
- Giving a new translation its own common record: leave the foreign key empty, it gets the one of its parent.
