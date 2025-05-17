--TEST--
Bug #18872 (class constant used as default parameter)
--FILE--
<?php
class FooBar {
    final const BIFF = 3;
}

function foo(int $biff = FooBar::BIFF) {
    echo $biff . "\n";
}

foo();
foo();
?>
--EXPECT--
3
3
