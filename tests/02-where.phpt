--TEST--
WHERE test coverage
--FILE--
<?php

foreach(array(
  array('id', 1),
  array('id !=', 1),
  array('title LIKE', 'Nee%'),
  array('id', true),
  array('id', false),
  array('id', null),
  array('id NOT', null),
  array('id', array(1, 2)),
  array('id NOT', array(1, 2)),
  array('id', new NeevoLiteral(99))
) as $condition){

  $query = $db->select('id', 'software')->where($condition[0], $condition[1])->order('id')->fetchArray();
  if(!$query)
    $query = array();
  foreach($query as $result){
    if(isset($result['id']))
      echo $result['id'];
    echo ',';
  }
  echo "\n";
  unset($query);
}

?>
--EXPECT--
1,
2,3,4,
1,
1,2,3,4,


1,2,3,4,
1,2,
3,4,

