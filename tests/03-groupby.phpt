--TEST--
GROUP BY testing
--FILE--
<?php

foreach($db->select('aid, SUM(id) as sum', 'software')->group('aid')->orderBy('aid ASC') as $r)
  echo "$r->aid-$r->sum\n";

?>
--EXPECT--
11-4
12-6
