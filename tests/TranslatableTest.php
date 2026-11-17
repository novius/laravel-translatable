<?php

namespace Novius\LaravelTranslatable\Tests;

use Illuminate\Support\Facades\DB;
use Novius\LaravelTranslatable\Exceptions\TranslatableException;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\CustomColumnsTranslatableModel;
use Workbench\App\Models\TranslatableModel;

class TranslatableTest extends TestCase
{
    /* --- Translatable Tests --- */

    #[Test]
    public function a_model_can_be_translate(): void
    {
        $model = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);

        $translateModel = $model->translate('en', [
            'title' => 'English',
        ]);

        $this->assertSame($model->fresh()->locale_parent_id, $model->fresh()->id);
        $this->assertSame($translateModel->fresh()->locale_parent_id, $model->fresh()->id);
        $this->assertSame($translateModel->fresh()->locale, 'en');
        $this->assertSame($translateModel->fresh()->title, 'English');
        $this->assertCount(2, $translateModel->fresh()->translations);
        $this->assertCount(2, $model->fresh()->translations);
    }

    #[Test]
    public function a_model_cant_be_translate_in_same_locale(): void
    {
        $model = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);

        $this->expectException(TranslatableException::class);

        $model->translate('fr');
    }

    #[Test]
    public function a_model_cant_have_multiple_translation_in_same_locale(): void
    {
        $model = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);

        $model->translate('en');

        $this->expectException(TranslatableException::class);

        $model->translate('en');
    }

    #[Test]
    public function a_model_can_have_no_translation(): void
    {
        $model1 = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);

        $model2 = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français alt',
        ]);

        $model3 = TranslatableModel::factory()->create([
            'locale' => 'en',
            'title' => 'English',
        ]);

        $this->assertCount(0, $model1->fresh()->translations);
        $this->assertCount(0, $model2->fresh()->translations);
        $this->assertCount(0, $model3->fresh()->translations);
    }

    #[Test]
    public function the_translations_loaded_before_translating_are_reloaded(): void
    {
        $model = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);
        $this->assertCount(0, $model->translations);

        $model->translate('en', [
            'title' => 'English',
        ]);

        $this->assertCount(2, $model->translations);
        $this->assertSame('English', $model->getTranslation('en')?->title);
    }

    #[Test]
    public function the_originals_are_the_models_not_translated_and_the_roots_of_their_translations(): void
    {
        $alone = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Seul',
        ]);
        $root = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);
        $translation = $root->translate('en', [
            'title' => 'English',
        ]);

        $this->assertEqualsCanonicalizing(
            [$alone->id, $root->id],
            TranslatableModel::query()->originals()->pluck('id')->all()
        );
        $this->assertTrue($alone->isOriginal());
        $this->assertTrue($root->isOriginal());
        $this->assertTrue($root->fresh()->isOriginal());
        $this->assertFalse($translation->isOriginal());
        $this->assertFalse($translation->fresh()->isOriginal());
    }

    #[Test]
    public function a_translation_created_with_its_parent_id_makes_the_parent_the_root_of_the_group(): void
    {
        $parent = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);

        $translation = TranslatableModel::query()->create([
            'locale' => 'en',
            'title' => 'English',
            'locale_parent_id' => $parent->id,
        ]);

        // The parent is updated in the database, not the instance already in memory
        $this->assertNull($parent->locale_parent_id);
        $this->assertSame($parent->id, $parent->fresh()->locale_parent_id);
        $this->assertSame($parent->id, $translation->locale_parent_id);
        $this->assertSame([$parent->id], TranslatableModel::query()->originals()->pluck('id')->all());
        $this->assertCount(2, $parent->fresh()->translations);
    }

    #[Test]
    public function a_failed_translation_rolls_back_its_transaction(): void
    {
        $model = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);

        try {
            // The creating event throws: the locale is not available
            $model->translate('de');
            $this->fail('The translation should have failed');
        } catch (TranslatableException) {
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(1, TranslatableModel::query()->count());
        $this->assertNull($model->fresh()->locale_parent_id);
    }

    #[Test]
    public function the_scope_with_locale_qualifies_its_column(): void
    {
        $model = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);
        $translation = $model->translate('en', [
            'title' => 'English',
        ]);

        // Both tables of the join have a locale column
        $ids = TranslatableModel::query()
            ->join('translatable_models as roots', 'roots.id', '=', 'translatable_models.locale_parent_id')
            ->withLocale('en')
            ->pluck('translatable_models.id')
            ->all();

        $this->assertSame([$translation->id], $ids);
    }

    #[Test]
    public function the_scopes_use_the_configured_columns(): void
    {
        $model = CustomColumnsTranslatableModel::query()->create([
            'lang' => 'fr',
            'title' => 'Français',
        ]);
        $translation = $model->translate('en', [
            'title' => 'English',
        ]);

        $this->assertSame($model->id, $model->fresh()->lang_parent_id);
        $this->assertSame([$translation->id], CustomColumnsTranslatableModel::query()->withLocale('en')->pluck('id')->all());
        $this->assertSame([$model->id], CustomColumnsTranslatableModel::query()->originals()->pluck('id')->all());
        $this->assertTrue($model->isOriginal());
        $this->assertFalse($translation->isOriginal());
    }
}
