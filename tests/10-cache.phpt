--TEST--
Neevo cache read/write test
--FILE--
<?php

$_SESSION = array(); // Headers already sent

$db->cacheSave('string', md5(time()));
$db->cacheSave('array', array('one', 'two'));
$db->cacheSave('object', (object) array('key' => 'value'));

// String
echo (strlen($db->cacheLoad('string')) == 32) ? 'string test ok' : 'string test failed';
echo "\n";

// Array
$arr = $db->cacheLoad('array');
echo (is_array($arr) && $arr[1] === 'two') ? 'array test ok' : 'array test failed';
echo "\n";

//Object
$obj = $db->cacheLoad('object');
echo ($obj instanceof stdClass && $obj->key === 'value') ? 'object test ok' : 'object test failed';

// Cleanup
unset($_SESSION, $arr, $obj);
?>
--EXPECT--
string test ok
array test ok
object test ok