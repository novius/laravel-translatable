<?php

namespace Novius\LaravelTranslatable\Tests;

use Novius\LaravelTranslatable\Tests\Models\TranslatableModel;
use RuntimeException;

class TranslatableTest extends TestCase
{
    /* --- Translatable Tests --- */

    /** @test */
    public function a_model_can_be_translate()
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

    public function a_model_cant_be_translate_in_same_locale()
    {
        $model = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);

        $this->expectException(RuntimeException::class);

        $model->translate('fr');
    }

    public function a_model_cant_have_multiple_translation_in_same_locale()
    {
        $model = TranslatableModel::factory()->create([
            'locale' => 'fr',
            'title' => 'Français',
        ]);

        $model->translate('en');

        $this->expectException(RuntimeException::class);

        $model->translate('en');
    }
}
