<?php

namespace Novius\LaravelTranslatable\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Novius\LaravelTranslatable\Tests\Models\TranslatableModel;

class TranslatableModelFactory extends Factory
{
    protected $model = TranslatableModel::class;

    public function definition(): array
    {
        return [];
    }
}
