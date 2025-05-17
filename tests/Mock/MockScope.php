<?php

namespace Tourze\Rector4KPHP\Tests\Mock;

class MockScope
{
    private array $definedVariables;
    private ?string $functionName;
    
    public function __construct(array $definedVariables = [], ?string $functionName = null)
    {
        $this->definedVariables = $definedVariables;
        $this->functionName = $functionName;
    }
    
    public function getDefinedVariables(): array
    {
        return $this->definedVariables;
    }
    
    public function getFunction(): ?MockFunction
    {
        if ($this->functionName === null) {
            return null;
        }
        
        return new MockFunction($this->functionName);
    }
}

class MockFunction
{
    private string $name;
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
} 