--TEST--
WHERE test coverage
--FILE--
<?php

foreach(array(
  array('id', 1),
  array('id != %1', 1),
  array('title LIKE %1', 'Nee%'),
  array('id', true),
  array('id', false),
  array('id', null),
  array('id IS NOT %1', null),
  array('id', array(1, 2)),
  array('id NOT %1', array(1, 2)),
  array('id', new NeevoLiteral(99))
) as $cond){

  $query = $db->select('id', 'software')->where($cond[0], $cond[1])->order('id')->fetch();
  if(!$query){
    $query = array();
  }
  foreach($query as $result){
    if(isset($result['id'])){
      echo $result['id'];
    }
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

