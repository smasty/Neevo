--TEST--
Testing conditional statements
--FILE--
<?php

// IF test
echo count($db->select('software')
  ->if(true)->limit(1)
  ->else()->limit(2)
  ->end()->fetchAll(Neevo::ASSOC)) === 1 ? "if ok\n" : "if failed\n";

// ELSE test
echo count($db->select('software')
  ->if(false)->limit(1)
  ->else()->limit(2)
  ->end()->fetchAll(Neevo::ASSOC)) === 2 ? "else ok\n" : "else failed\n";

?>
--EXPECT--
if ok
else ok
