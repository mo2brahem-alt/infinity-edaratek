<?php

namespace App\Support;

final class NativeId
{
    public static function parse(string $id): int
    {
        abort_unless(preg_match('/^[1-9][0-9]*$/D', $id), 404);
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]);
        abort_if($value === false, 404);
        return $value;
    }
}
