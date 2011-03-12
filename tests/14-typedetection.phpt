--TEST--
Type detection - automated and user-defined
--FILE--
<?php

// Automated detection
$a = $db->select(':id, :author_id, :title', 'software')->detectTypes()->fetch();

echo (is_int($a->id) && is_int($a->author_id) && is_string($a->title))
	? "auto-detection ok\n" : "auto-detection failed\n";


// User-defiend detection
$u = $db->select(':id, :author_id, :title, 1 as :true, 0 as :false', 'software')->setTypes(array(
	'id' => Neevo::FLOAT,
	'author_id' => Neevo::INT,
	'title' => Neevo::TEXT,
	'true' => Neevo::BOOL,
	'false' => Neevo::BOOL
))->fetch();

echo (is_float($u->id) && is_int($u->author_id) && is_string($u->title)
		&& $u->true === true && $u->false === false)
	? "user-defined detection ok\n" : "user-defined detection failed\n";

unset($a, $u);

?>
--EXPECT--
auto-detection ok
user-defined detection ok
