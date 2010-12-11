--TEST--
NeevoResult::setRowClass test
--FILE--
<?php

class Row{
  function __construct(array $data){
    foreach($data as $key => $val){
      $this->$key = $val;
    }
  }
}

// Try setting existing class
$row = $db->select('id, title', 'software')->limit(1)->setRowClass('Row')->fetchRow();
echo ($row instanceof Row ? 'setting passed' : 'setting failed') . "\n";
unset($row);

// Try setting class which does not exist.
try{
  $row = $db->select('id, title', 'software')->limit(1)->setRowClass('NoClass')->fetchRow();
} catch(NeevoException $e){
  echo (strstr($e->getMessage(), 'NoClass') ? 'not-existing catched' : 'not-existing catched, wrong message.') . "\n";
}

?>
--EXPECT--
setting passed
not-existing catched
