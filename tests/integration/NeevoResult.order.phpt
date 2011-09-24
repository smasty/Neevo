--TEST--
ORDER BY test coverage
--FILE--
<?php
require_once __DIR__ . '/config.php';

foreach(array(

	array(':id', Neevo\Manager::ASC),
	array(':id', Neevo\Manager::DESC),
	array(array(
		':author_id' => 'DESC',
		':url' => null), null),
	array(array(
		':author_id' => null,
		':url' => null), null)

) as $order){
	foreach($db->select(':id', ':software')->order($order[0], $order[1]) as $result){
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
