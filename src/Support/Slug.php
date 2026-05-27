<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Support;

final class Slug
{
    public static function from(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'item';
    }
}
