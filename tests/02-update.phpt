--TEST
Insert, Update, Delete
--CODE
<?php

$insert = db()->insertInto('software')
              ->values(array(
                'id' => 5,
                'aid' => 13,
                'title' => 'Debian',
                'web' => 'http://debian.org'))
              ->affectedRows();

echo "Insert: affected $insert rows.\n";

$update = db()->update('software')
              ->set(array(
                'slogan' => 'Stupid content tracker '.rand(0,999)))
              ->where('title', 'Git')->affectedRows();

echo "Update: affected $update rows.\n";

$delete = db()->delete('software')
              ->where('web LIKE', '%debian.org')->affectedRows();

echo "Delete: affected $delete rows.\n";

$select = db()->select('*')->from('software')->where('id', 5)->rows();

echo "Select: $select rows found.\n";
?>
--RESULT
Insert: affected 1 rows.
Update: affected 1 rows.
Delete: affected 1 rows.
Select: 0 rows found.
