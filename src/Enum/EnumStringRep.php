<?php

namespace Tourze\Rector4KPHP\Enum;

class EnumStringRep
{
    private static array $instances = [];

    private function __construct(public readonly string $value)
    {
    }

    public static function ABC(): self
    {
        if (!isset(self::$instances['abc'])) {
            self::$instances['abc'] = new self('abc');
        }
        return self::$instances['abc'];
    }

    public static function DEF(): self
    {
        if (!isset(self::$instances['def'])) {
            self::$instances['def'] = new self('def');
        }
        return self::$instances['def'];
    }

    /**
     * @return self[]
     */
    public static function cases(): array
    {
        return [
            self::ABC(),
            self::DEF(),
        ];
    }

    public static function from(string $value): self
    {
        foreach (self::cases() as $v) {
            if ($v->value === $value) {
                return $v;
            }
        }
        throw new \ValueError();
    }

    public static function tryFrom(string $value): ?self
    {
        try {
            return self::from($value);
        } catch (\ValueError $exception) {
            return null;
        }
    }

    public function toLabel(): string
    {
        return match ($this) {
            self::ABC() => 'ABC____',
            EnumStringRep::DEF() => 'DEF____',
        };
    }
}
