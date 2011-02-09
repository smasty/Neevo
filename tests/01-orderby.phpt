--TEST--
ORDER BY test coverage
--FILE--
<?php

foreach(array(
  ':id ASC',
  ':id DESC',
  array(':author_id DESC', ':url'),
  array(':author_id', ':url')
) as $order){
  foreach($db->select(':id', ':software')->order($order) as $result){
    if(isset($result['id'])){
      echo $result['id'];
    }
    echo ',';
  }
  echo "\n";
}

?>
--EXPECT--
1,2,3,4,5,6,
6,5,4,3,2,1,
5,6,4,3,2,1,
2,1,4,3,5,6,
