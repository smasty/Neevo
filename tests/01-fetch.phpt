--TEST
Fetch, FetchAssoc, FetchSingle, FetchRow
--CODE
<?php

// Base fetch
$fetch = db()->select('id, name, web', 'author')->orderBy('id ASC')->limit(2);
foreach($fetch as $row){
  echo "$row->id: {$row['name']}\n";
}

echo "\n";

// Associated fetch
$fetchAssoc = db()->select('name, web', 'author')->orderBy('id ASC')->fetchAssoc('id');
foreach($fetchAssoc as $key=>$val){
  echo "$val->name ($key): $val->web\n";
}

echo "\n";

// Single fetch
$single = db()->select('name', 'author')->where('id', 11)->fetchSingle();
echo "$single\n";

// First row fetch
$row = db()->select('id, title, web', 'software')->where('title', 'Neevo')->fetchRow();

echo "$row->id: $row->title ($row->web)\n";

?>
--RESULT
11: Martin Srank
12: Linus Torvalds

Martin Srank (11): http://smasty.net
Linus Torvalds (12): http://torvalds-family.blogspot.com

Martin Srank
1: Neevo (http://neevo.smasty.net)
