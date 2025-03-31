<?php

namespace Tourze\Rector4KPHP\Enum;

enum EnumStringRaw: string
{
    case ABC = 'abc9999999999999999';
    case DEF = 'def5555555555555555';

    /**
     * 读取label
     */
    public function toLabel(): string
    {
        return match ($this) {
            self::ABC => 'ABC____',
            EnumStringRaw::DEF => 'DEF____',
        };
    }

    public static function getLabel1(EnumStringRaw $enum = EnumStringRaw::ABC): string
    {
        return $enum->toLabel();
    }

    public static function test_getLabel1(): void
    {
        self::getLabel1();
        self::getLabel1(EnumStringRaw::DEF);
    }

    public static function getLabel2(EnumStringRaw|string $enum = EnumStringRaw::ABC): string
    {
        if (is_string($enum)) {
            $enum = EnumStringRaw::from($enum);
        }
        return $enum->toLabel();
    }

    public static function test_getLabel2(): void
    {
        self::getLabel2();
        self::getLabel2(EnumStringRaw::DEF);
    }

    public static function getLabel3(?EnumStringRaw $enum = EnumStringRaw::ABC): string
    {
        if ($enum === null) {
            $enum = EnumStringRaw::ABC;
        }
        return $enum->toLabel();
    }

    public static function test_getLabel3(): void
    {
        self::getLabel3();
        self::getLabel3(EnumStringRaw::DEF);
    }
}
