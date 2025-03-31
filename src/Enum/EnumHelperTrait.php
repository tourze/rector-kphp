<?php

namespace Tourze\Rector4KPHP\Enum;

trait EnumHelperTrait
{
    private function isEnum(string $name): bool
    {
        try {
            $reflection = new \ReflectionClass($name);
            return $reflection->isEnum();
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
