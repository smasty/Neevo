--TEST--
WHERE test coverage
--FILE--
<?php

require __DIR__ . '/../../src/neevo.php';
$db = new Neevo(array(
	'driver' => 'sqlite',
	'file' => __DIR__ . '/sqlite.db'
));

foreach(array(
	array(':id', 1),
	array(':id != %i', 1),
	array(':title LIKE %s', 'Drup%'),
	//array(':id', true),
	//array(':id', false),
	array(':id', null),
	array(':id IS NOT %', null),
	array(':id', array(1, 2)),
	array(':id NOT IN %a', array(1, 2)),
	array(':id', new NeevoLiteral(99))
) as $cond){

	$query = $db->select(':id', 'software')->where($cond)->order(':id');
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
2,3,4,5,6,
3,

1,2,3,4,5,6,
1,2,
3,4,5,6,

