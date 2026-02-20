<?php

namespace Novius\LaravelTranslatable\Tests;

use Novius\LaravelTranslatable\Exceptions\TranslatableException;
use PHPUnit\Framework\Attributes\Test;
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
}
