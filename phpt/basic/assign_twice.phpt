--TEST--
连续两次赋值
--FILE--
<?php

$a = 1;
echo $a;
$a = 2;
echo $a;

 ?>
--EXPECT--
12
