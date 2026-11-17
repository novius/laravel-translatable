<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Novius\LaravelTranslatable\Factories\MakesTranslations;
use Workbench\App\Models\TranslatableModel;

/**
 * @extends Factory<TranslatableModel>
 */
class TranslatableModelFactory extends Factory
{
    use MakesTranslations;

    protected $model = TranslatableModel::class;

    public function definition(): array
    {
        return [];
    }
}
