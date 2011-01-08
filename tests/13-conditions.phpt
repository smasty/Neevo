--TEST--
Testing conditional statements
--FILE--
<?php

// IF test
echo $db->select('software')
  ->if(true)->limit(1)
  ->else()->limit(2)
  ->end()->getLimit() === 1 ? "if ok\n" : "if failed\n";

// ELSE test
echo $db->select('software')
  ->if(false)->limit(1)
  ->else()->limit(2)
  ->end()->getLimit() === 2 ? "else ok\n" : "else failed\n";

// ELSEIF test
echo $db->select('software')
  ->if(false)->limit(1)
  ->elseif(true)->limit(3)
  ->else()->limit(2)
  ->end()->getLimit() === 3 ? "elseif ok\n" : "elseif failed\n";

?>
--EXPECT--
if ok
else ok
elseif ok
