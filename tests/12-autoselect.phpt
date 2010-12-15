--TEST--
SELECT with only table name passed.
--FILE--
<?php

echo join(',', array_keys($db->select('software')
  ->order('id')->limit(1)->fetchRow(Neevo::ASSOC))) . "\n";

try{
  $db->select();
} catch(InvalidArgumentException $e){
  echo "catched\n";
}
?>
--EXPECT--
id,aid,title,web,slogan
catched
