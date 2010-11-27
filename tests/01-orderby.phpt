--TEST--
ORDER BY test coverage
--FILE--
<?php

foreach(array(
  'id ASC',
  'id DESC',
  array('aid DESC', 'web'),
  array('aid', 'web')
) as $order){
  foreach($db->select('id', 'software')->order($order)->fetchArray() as $result){
    if(isset($result['id']))
      echo $result['id'];
    echo ',';
  }
  echo "\n";
}

?>
--EXPECT--
1,2,3,4,
4,3,2,1,
4,2,3,1,
3,1,4,2,
