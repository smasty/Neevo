--TEST
Result manipulating
--CODE
<?php

$author = db()->select('id', 'author')->where('name', 'Martin Srank')->fetchSingle();
$result = db()->select('*', 'software')->where('aid', $author)->orderBy('id ASC')->fetch();

$row = $result[0];
$row->slogan = 'Tiny database abstraction layer '.rand(0, 999);

echo "Selected ".count($result)." rows.\n";


// Only if driver supports this
if(in_array(driver(), array('mysql', 'mysqli'))){
  $affected = $row->update();

  $current = db()->select('slogan', 'software')
                 ->where('slogan LIKE', 'Tiny database abstraction layer%')
                 ->rows();

  echo "Updated $affected rows.\n";
  echo "Selected: $current.\n";
}
?>
--RESULT
Selected 2 rows.
<?php if(in_array(driver(), array('mysql', 'mysqli'))){ ?>
Updated 1 rows.
Selected: 1.
<?php } ?>