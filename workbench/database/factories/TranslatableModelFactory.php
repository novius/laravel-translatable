<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\TranslatableModel;

class TranslatableModelFactory extends Factory
{
    protected $model = TranslatableModel::class;

    public function definition(): array
    {
        return [];
    }
}
