--TEST--
Type detection - automated and user-defined
--FILE--
<?php

// Automated detection
$a = $db->select('id, aid, title', 'software')->detectTypes()->fetch();
echo (is_int($a->id) && is_int($a->aid) && is_string($a->title))
  ? "auto-detection ok\n" : "auto-detection failed\n";

// User-defiend detection
$u = $db->select('id, aid, title, 1 as t, 0 as f', 'software')->setTypes(array(
  'id' => Neevo::FLOAT,
  'aid' => Neevo::INT,
  'title' => Neevo::TEXT,
  't' => Neevo::BOOL,
  'f' => Neevo::BOOL
))->fetch();
echo (is_float($u->id) && is_int($u->aid) && is_string($u->title) && $u->t === true && $u->f === false)
  ? "user-defined detection ok\n" : "user-defined detection failed\n";

unset($a, $u);
?>
--EXPECT--
auto-detection ok
user-defined detection ok
