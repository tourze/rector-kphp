<?php

namespace Tourze\Rector4KPHP\Tests\Mock;

class MockSymplifyRuleDefinition
{
    private string $description;
    
    public function __construct(string $description) 
    {
        $this->description = $description;
    }
    
    public function getDescription(): string
    {
        return $this->description;
    }
} 