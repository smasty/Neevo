--TEST--
SELECT with only table name passed.
--FILE--
<?php

echo join(',', array_keys($db->select('software')
  ->order(':id')->limit(1)->fetch()->toArray())) . "\n";

try{
  $db->select();
} catch(InvalidArgumentException $e){
  echo "catched\n";
}
?>
--EXPECT--
id,author_id,title,url
catched
