<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://neevo.smasty.net
 * @package  Neevo
 *
 */class
NeevoStatic{private
static$highlight_classes=array('columns'=>'sql-col','chars'=>'sql-char','keywords'=>'sql-kword','joins'=>'sql-join','functions'=>'sql-func','constants'=>'sql-const');private
static$sql_functions=array('MIN','MAX','SUM','COUNT','AVG','CAST','COALESCE','CHAR_LENGTH','LENGTH','SUBSTRING','DAY','MONTH','YEAR','DATE_FORMAT','CRC32','CURDATE','SYSDATE','NOW','GETDATE','FROM_UNIXTIME','FROM_DAYS','TO_DAYS','HOUR','IFNULL','ISNULL','NVL','NVL2','INET_ATON','INET_NTOA','INSTR','FOUND_ROWS','LAST_INSERT_ID','LCASE','LOWER','UCASE','UPPER','LPAD','RPAD','RTRIM','LTRIM','MD5','MINUTE','ROUND','SECOND','SHA1','STDDEV','STR_TO_DATE','WEEK','RAND');public
static
function
highlight_sql($sql){$classes=self::$highlight_classes;unset($classes['columns']);$words=array('keywords'=>array('SELECT','UPDATE','INSERT','DELETE','REPLACE','INTO','CREATE','ALTER','TABLE','DROP','TRUNCATE','FROM','ADD','CHANGE','COLUMN','KEY','WHERE','ON','CASE','WHEN','THEN','END','ELSE','AS','USING','USE','INDEX','CONSTRAINT','REFERENCES','DUPLICATE','LIMIT','OFFSET','SET','SHOW','STATUS','BETWEEN','AND','IS','NOT','OR','XOR','INTERVAL','TOP','GROUP BY','ORDER BY','DESC','ASC','COLLATE','NAMES','UTF8','DISTINCT','DATABASE','CALC_FOUND_ROWS','SQL_NO_CACHE','MATCH','AGAINST','LIKE','REGEXP','RLIKE','PRIMARY','AUTO_INCREMENT','DEFAULT','IDENTITY','VALUES','PROCEDURE','FUNCTION','TRAN','TRANSACTION','COMMIT','ROLLBACK','SAVEPOINT','TRIGGER','CASCADE','DECLARE','CURSOR','FOR','DEALLOCATE'),'joins'=>array('JOIN','INNER','OUTER','FULL','NATURAL','LEFT','RIGHT'),'functions'=>self::$sql_functions,'chars'=>'/([\\.,!\\(\\)<>:=`]+)/i','constants'=>'/(\'[^\']*\'|[0-9]+)/i');$sql=str_replace('\\\'','\\&#039;',$sql);foreach($classes
as$key=>$class){$regexp=in_array($key,array('constants','chars'))?$words[$key]:'/\\b('.join("|",$words[$key]).')\\b/i';$sql=preg_replace($regexp,"<span class=\"$class\">$1</span>",$sql);}$sql=str_replace($chcolors,$hcolors,$sql);return"<code class=\"".self::$highlight_classes['columns']."\"> $sql </code>\n";}public
static
function
escape_array(array$array,Neevo$neevo){foreach($array
as&$value){$value=is_numeric($value)?$value:(is_string($value)?self::escape_string($value,$neevo):(is_array($value)?self::escape_array($value):$value));}return$array;}public
static
function
escape_string($string,Neevo$neevo){if(get_magic_quotes_gpc())$string=stripslashes($string);$string=$neevo->driver()->escape_string($string);return
is_numeric($string)?$string:(is_string($string)?(self::is_sql_func($string)?self::quote_sql_func($string):"'$string'"):$string);}public
static
function
is_sql_func($string){if(is_string($string)){$is_plmn=preg_match("/^(\w*)(\+|-)(\w*)/",$string);$var=strtoupper(preg_replace('/[^a-zA-Z0-9_\(\)]/','',$string));$is_sql=in_array(preg_replace('/\(.*\)/','',$var),self::$sql_functions);return($is_sql||$is_plmn);}else
return
false;}public
static
function
quote_sql_func($sql_func){return
str_replace(array('("','")'),array('(\'','\')'),$sql_func);}public
static
function
is_as_constr($string){return(bool)preg_match('/(.*) as \w*/i',$string);}public
static
function
quote_as_constr($as_constr,$col_quote){$construction=explode(' ',$as_constr);$escape=preg_match('/^\w{1,}$/',$construction[0])?true:false;if($escape){$construction[0]=$col_quote.$construction[0].$col_quote;}$as_constr=join(' ',$construction);return
preg_replace('/(.*) (as) (\w*)/i','$1 AS '.$col_quote.'$3'.$col_quote,$as_constr);}public
static
function
filesize($bytes){$unit=array('B','kB','MB','GB','TB','PB');return@round($bytes/pow(1024,($i=floor(log($bytes,1024)))),2).' '.$unit[$i];}}class
NeevoQuery{public$table,$type,$limit,$offset,$neevo,$resource,$time,$sql;public$where,$order,$columns,$data=array();function
__construct(Neevo$object,$type='',$table=''){$this->neevo=$object;$this->type($type);$this->table($table);}public
function
set_time($time){$this->time=$time;}public
function
time(){return$this->time;}public
function
table($table){$this->table=$table;return$this;}public
function
type($type){$this->type=$type;return$this;}public
function
sql($sql){$this->sql=$sql;return$this;}public
function
cols($columns){if(!is_array($columns))$columns=explode(',',$columns);$this->columns=$columns;return$this;}public
function
data(array$data){$this->data=$data;return$this;}public
function
where($where,$value,$glue=null){$where_condition=explode(' ',$where);if(is_null($value)){$where_condition[1]="IS";$value="NULL";}if(is_array($value))$where_condition[1]="IN";if(!isset($where_condition[1]))$where_condition[1]='=';$column=$where_condition[0];$condition=array($column,$where_condition[1],$value,strtoupper($glue?$glue:"and"));$this->where[]=$condition;return$this;}public
function
order($args){$rules=array();$arguments=func_get_args();foreach($arguments
as$argument){$order_rule=explode(' ',$argument);$rules[]=$order_rule;}$this->order=$rules;return$this;}public
function
limit($limit,$offset=null){$this->limit=$limit;if(isset($offset)&&$this->type=='select')$this->offset=$offset;return$this;}public
function
dump($color=true,$return_string=false){$code=$color?NeevoStatic::highlight_sql($this->build()):$this->build();if(!$return_string)echo$code;return$return_string?$code:$this;}public
function
run($catch_error=false){$start=explode(" ",microtime());$query=$this->neevo->driver()->query($this->build(),$this->neevo->resource());if(!$query){$this->neevo->error('Query failed',$catch_error);return
false;}else{$this->neevo->increment_queries();$this->neevo->set_last($this);$end=explode(" ",microtime());$time=round(max(0,$end[0]-$start[0]+$end[1]-$start[1]),4);$this->set_time($time);$this->resource=$query;return$query;}}public
function
fetch(){$resource=is_resource($this->resource)?$this->resource:$this->run();while($tmp_rows=$this->neevo->driver()->fetch($resource))$rows[]=(count($tmp_rows)==1)?$tmp_rows[max(array_keys($tmp_rows))]:$tmp_rows;$this->neevo->driver()->free($resource);if(count($rows)==1)$rows=$rows[0];if(!count($rows)&&is_array($rows))return
false;return$resource?$rows:$this->neevo->error("Fetching result data failed");}public
function
seek($row_number){if(!is_resource($this->resource))$this->run();$seek=$this->neevo->driver()->seek($this->resource,$row_number);return$seek?$seek:$this->neevo->error("Cannot seek to row $row_number");}public
function
rand(){$this->neevo->driver()->rand($this);return$this;}public
function
rows(){return$this->neevo->driver()->rows($this,$string);}public
function
info(){$exec_time=$this->time()?$this->time():-1;$rows=$this->time()?$this->rows():-1;$info=array('resource'=>$this->neevo->resource(),'query'=>$this->dump($html,true),'exec_time'=>$exec_time,'rows'=>$rows);if($this->type=='select')$info['query_resource']=$this->resource;return$info;}public
function
undo($sql_part,$position=1){switch(strtolower($sql_part)){case'where':$part='where';break;case'order';$part='order';break;case'column';$part='columns';break;case'value';$part='data';break;case'limit':$part='limit';$str=true;break;case'offset':$part='offset';$str=true;break;default:$this->neevo->error("Undo failed: No such Query part '$sql_part' supported for undo()",true);break;}if($str)unset($this->$part);else{if(isset($this->$part)){$positions=array();if(!is_array($position))$positions[]=$position;foreach($positions
as$pos){$pos=is_numeric($pos)?$pos-1:$pos;$apart=$this->$part;unset($apart[$pos]);foreach($apart
as$key=>$value){$loop[$key]=$value;}$this->$part=$loop;}}else$this->neevo->error("Undo failed: No such Query part '$sql_part' for this kind of Query",true);}return$this;}public
function
build(){return$this->neevo->driver()->build($this);}}interface
INeevoDriver{public
function
__construct($neevo);public
function
connect(array$opts);public
function
close($resource);public
function
free($result);public
function
query($query_string,$resource);public
function
error($neevo_msg,$warning=false);public
function
fetch($resource);public
function
seek($resource,$row_number);public
function
rand(NeevoQuery$query);public
function
rows(NeevoQuery$query);public
function
build(NeevoQuery$query);public
function
escape_string($string);}class
NeevoDriverMySQL
implements
INeevoDriver{const
COL_QUOTE='`';private$neevo;public
function
__construct($neevo){if(!extension_loaded("mysql"))throw
new
NeevoException("PHP extension 'mysql' not loaded.");$this->neevo=$neevo;}public
function
connect(array$opts){$connection=@mysql_connect($opts['host'],$opts['username'],$opts['password']);if(!is_resource($connection))$this->neevo->error("Connection to host '".$opts['host']."' failed");if($opts['database']){$db=mysql_select_db($opts['database']);if(!$db)$this->neevo->error("Could not select database '{$opts['database']}'");}$this->neevo->set_resource($connection);$this->neevo->set_options($opts);if($opts['encoding']){if(function_exists('mysql_set_charset'))$ok=@mysql_set_charset($opts['encoding'],$this->neevo->resource());if(!$ok)$this->neevo->sql("SET NAMES ".$opts['encoding'])->run();}return(bool)$connection;}public
function
close($resource){@mysql_close($resource);}public
function
free($result){return@mysql_free_result($result);}public
function
query($query_string,$resource){return@mysql_query($query_string,$resource);}public
function
error($neevo_msg,$warning=false){$mysql_msg=mysql_error();$mysql_msg=str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use','Syntax error',$mysql_msg);$msg="$neevo_msg. $mysql_msg";$mode=$this->neevo->error_reporting();if($mode!=Neevo::E_NONE){if(($mode!=Neevo::E_STRICT&&$catch)||$mode==Neevo::E_CATCH){call_user_func($this->neevo->error_handler(),$msg);}else
throw
new
NeevoException($msg);}return
false;}public
function
fetch($resource){return@mysql_fetch_assoc($resource);}public
function
seek($resource,$row_number){return@mysql_data_seek($resource,$row_number);}public
function
rand(NeevoQuery$query){$query->order('RAND()');}public
function
rows(NeevoQuery$query){if($query->type!='select')$aff_rows=$query->time()?@mysql_affected_rows($query->neevo->resource()):false;else$num_rows=@mysql_num_rows($query->resource);if($num_rows||$aff_rows)return$num_rows?$num_rows:$aff_rows;else
return
false;}public
function
build(NeevoQuery$query){if($query->sql)$q=$query->sql;else{$table=$this->build_tablename($query);if($query->where)$where=$this->build_where($query);if($query->order)$order=$this->build_order($query);if($query->limit)$limit=" LIMIT ".$query->limit;if($query->offset)$limit.=" OFFSET ".$query->offset;if($query->type=='select'){$cols=$this->build_select_cols($query);$q.="SELECT $cols FROM $table$where$order$limit";}if($query->type=='insert'&&$query->data){$insert_data=$this->build_insert_data($query);$q.="INSERT INTO $table$insert_data";}if($query->type=='update'&&$query->data){$update_data=$this->build_update_data($query);$q.="UPDATE $table$update_data$where$order$limit";}if($query->type=='delete')$q.="DELETE FROM $table$where$order$limit";}return"$q;";}public
function
escape_string($string){return
mysql_real_escape_string($string);}private
function
build_tablename(NeevoQuery$query){$pieces=explode(".",$query->table);$prefix=$query->neevo->prefix();if($pieces[1])return
self::COL_QUOTE.$pieces[0].self::COL_QUOTE.".".self::COL_QUOTE.$prefix.$pieces[1].self::COL_QUOTE;else
return
self::COL_QUOTE.$prefix.$pieces[0].self::COL_QUOTE;}private
function
build_where(NeevoQuery$query){$prefix=$query->neevo->prefix();foreach($query->where
as$where){if(is_array($where[2])){$where[2]="(".join(", ",NeevoStatic::escape_array($where[2],$this->neevo)).")";$in_construct=true;}$wheres[]=$where;}unset($wheres[count($wheres)-1][3]);foreach($wheres
as$in_where){if(NeevoStatic::is_sql_func($in_where[0]))$in_where[0]=NeevoStatic::quote_sql_func($in_where[0]);if(strstr($in_where[0],"."))$in_where[0]=preg_replace("#([0-9A-Za-z_]{1,64})(\.)([0-9A-Za-z_]+)#",self::COL_QUOTE."$prefix$1".self::COL_QUOTE.".".self::COL_QUOTE."$3".self::COL_QUOTE,$in_where[0]);else$in_where[0]=self::COL_QUOTE.$in_where[0].self::COL_QUOTE;if(!$in_construct)$in_where[2]=NeevoStatic::escape_string($in_where[2],$this->neevo);$wheres2[]=join(' ',$in_where);}foreach($wheres2
as&$rplc_where){$rplc_where=str_replace(array(' = ',' != '),array('=','!='),$rplc_where);}return" WHERE ".join(' ',$wheres2);}private
function
build_insert_data(NeevoQuery$query){foreach(NeevoStatic::escape_array($query->data,$this->neevo)as$col=>$value){$cols[]=self::COL_QUOTE.$col.self::COL_QUOTE;$values[]=$value;}return" (".join(', ',$cols).") VALUES (".join(', ',$values).")";}private
function
build_update_data(NeevoQuery$query){foreach(NeevoStatic::escape_array($query->data,$this->neevo)as$col=>$value){$update[]=self::COL_QUOTE.$col.self::COL_QUOTE."=".$value;}return" SET ".join(', ',$update);}private
function
build_order(NeevoQuery$query){foreach($query->order
as$in_order){$in_order[0]=(NeevoStatic::is_sql_func($in_order[0]))?$in_order[0]:self::COL_QUOTE.$in_order[0].self::COL_QUOTE;$orders[]=join(' ',$in_order);}return" ORDER BY ".join(', ',$orders);}private
function
build_select_cols(NeevoQuery$query){$prefix=$query->neevo->prefix();foreach($query->columns
as$col){$col=trim($col);if($col!='*'){if(strstr($col,".*")){$col=preg_replace("#([0-9A-Za-z_]+)(\.)(\*)#",self::COL_QUOTE."$prefix$1".self::COL_QUOTE.".*",$col);}else{if(strstr($col,"."))$col=preg_replace("#([0-9A-Za-z_]{1,64})(\.)([0-9A-Za-z_]+)#",self::COL_QUOTE."$prefix$1".self::COL_QUOTE.".".self::COL_QUOTE."$3".self::COL_QUOTE,$col);if(NeevoStatic::is_as_constr($col))$col=NeevoStatic::quote_as_constr($col,self::COL_QUOTE);elseif(NeevoStatic::is_sql_func($col))$col=NeevoStatic::quote_sql_func($col);elseif(!strstr($col,"."))$col=self::COL_QUOTE.$col.self::COL_QUOTE;}}$cols[]=$col;}return
join(', ',$cols);}}class
Neevo{private$resource,$last,$table_prefix,$queries,$error_reporting,$driver,$error_handler;private$options=array();const
E_NONE=1;const
E_CATCH=2;const
E_WARNING=3;const
E_STRICT=4;const
VERSION="0.2dev";const
REVISION=78;public
function
__construct(array$opts){$this->set_driver($opts['driver']);$this->connect($opts);if($opts['error_reporting'])$this->error_reporting=$opts['error_reporting'];if($opts['table_prefix'])$this->table_prefix=$opts['table_prefix'];}public
function
__destruct(){$this->driver()->close($this->resource);}private
function
connect(array$opts){return$this->driver()->connect($opts);}private
function
set_driver($driver){if(!$driver)throw
new
NeevoException("Driver not set.");switch(strtolower($driver)){case"mysql":$this->driver=new
NeevoDriverMySQL($this);break;default:throw
new
NeevoException("Driver $driver not supported.");break;}}public
function
driver(){return$this->driver;}public
function
set_resource($resource){$this->resource=$resource;}public
function
resource(){return$this->resource;}public
function
set_options(array$opts){$this->options=$opts;}public
function
set_prefix($prefix){$this->table_prefix=$prefix;}public
function
prefix(){return$this->table_prefix;}public
function
set_error_reporting($value){$this->error_reporting=$value;if(!isset($this->error_reporting))$this->error_reporting=self::E_WARNING;}public
function
error_reporting(){if(!isset($this->error_reporting))$this->error_reporting=self::E_WARNING;return$this->error_reporting;}public
function
set_error_handler($handler_function){if(function_exists($handler_function))$this->error_handler=$handler_function;else$this->error_handler=array('Neevo','default_error_handler');}public
function
error_handler(){if(!function_exists($this->error_handler))$this->error_handler=array('Neevo','default_error_handler');return$this->error_handler;}public
function
set_last(NeevoQuery$last){$this->last=$last;}public
function
last(){return$this->last;}public
function
increment_queries(){$this->queries++;}public
function
queries(){return$this->queries;}public
function
select($columns,$table){$q=new
NeevoQuery($this,'select',$table);return$q->cols($columns);}public
function
insert($table,array$data){$q=new
NeevoQuery($this,'insert',$table);return$q->data($data);}public
function
update($table,array$data){$q=new
NeevoQuery($this,'update',$table);return$q->data($data);}public
function
delete($table){return
new
NeevoQuery($this,'delete',$table);}public
function
sql($sql){$q=new
NeevoQuery($this);return$q->sql($sql);}public
function
error($neevo_msg,$warning=false){return$this->driver()->error($neevo_msg,$warning);}public
function
info(){$info=$this->options;unset($info['password']);$info['queries']=$this->queries();$info['last']=$this->last();$info['table_prefix']=$this->prefix();$info['error_reporting']=$this->error_reporting();$info['memory_usage']=$this->memory();$info['version']=$this->version();return$info;}public
static
function
default_error_handler($msg){echo"<b>Neevo error:</b> $msg.\n";}public
function
memory(){return
NeevoStatic::filesize(memory_get_usage(true));}public
function
version(){return"Neevo ".self::VERSION." (revision ".self::REVISION.").";}}class
NeevoException
extends
Exception{};?>