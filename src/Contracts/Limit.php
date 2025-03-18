<?php

namespace Tolery\AiCad\Contracts;

interface Limit
{
    public static function create(array $data): self;

    public static function updateOrCreate(array $data, bool $throw): self;

    public static function findByName(string $name, ?string $plan): self;

    public static function findById(int $id): self;

    public function incrementBy(float|int $amount = 1.0): bool;

    public function decrementBy(float|int $amount = 1.0): bool;
}
