<?php

namespace Tourze\Rector4KPHP\Tests\Printer;

use PHPUnit\Framework\TestCase;
use Tourze\Rector4KPHP\Printer\CppPrinter;
use Twig\Environment;

class CppPrinterTest extends TestCase
{
    private CppPrinter $printer;
    private Environment $twigEnvironment;
    
    protected function setUp(): void
    {
        $this->twigEnvironment = $this->createMock(Environment::class);
        $this->printer = new CppPrinter($this->twigEnvironment);
    }
    
    public function testPopCounter(): void
    {
        $counter1 = $this->printer->popCounter();
        $counter2 = $this->printer->popCounter();
        
        $this->assertGreaterThan($counter1, $counter2);
        $this->assertEquals($counter1 + 1, $counter2);
    }
} 