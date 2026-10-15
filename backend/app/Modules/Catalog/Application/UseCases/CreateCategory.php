<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Models\Category;
use App\Modules\Catalog\Domain\Contracts\CategoryRepository;
use Illuminate\Validation\ValidationException;

class CreateCategory
{
    public function __construct(
        private CategoryRepository $categories
    ) {}

    public function execute(array $data): Category
    {
        $name = trim($data['name']);

        if ($this->categories->existsByName($name)) {
            throw ValidationException::withMessages([
                'name' => ['Ya existe una categoría con ese nombre.'],
            ]);
        }

        return $this->categories->create([
            'name' => $name,
            'description' => $data['description'] ?? null,
        ]);
    }
}
