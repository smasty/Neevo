--TEST
Fetch, FetchAssoc, FetchSingle
--CODE
<?php

// Base fetch
$fetch = db()->select('id, name, web')->from('author')->orderBy('id ASC')->limit(2)->fetch();
if($fetch instanceof NeevoResult){
  foreach($fetch as $row){
    echo "$row->id: {$row['name']}\n";
  }
}

echo "\n";

// Associated fetch
$fetchAssoc = db()->select('name, web')->from('author')->orderBy('id ASC')->fetchAssoc('id');
foreach($fetchAssoc as $key=>$val){
  echo "$val->name ($key): $val->web\n";
}

echo "\n";

// Single fetch
$single = db()->select('name')->from('author')->where('id', 11)->fetchSingle();
echo "$single\n";

?>
--RESULT
11: Martin Srank
12: Linus Torvalds

Martin Srank (11): http://smasty.net
Linus Torvalds (12): http://torvalds-family.blogspot.com

Martin Srank
