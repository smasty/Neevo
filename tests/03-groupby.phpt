--TEST--
GROUP BY testing
--FILE--
<?php

$g = db()->select('aid, SUM(id) as sum', 'software')->group('aid')->orderBy('aid ASC');

foreach($g as $r)
  echo "$r->aid-$r->sum\n";

?>
--EXPECT--
11-4
12-6
