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
 * @link     http://neevo.smasty.net/
 * @package  Neevo
 *
 */if(version_compare(PHP_VERSION,'5.1.0','<')){if(version_compare(PHP_VERSION,'5.0.0','>='))throw
new
Exception('Neevo requires PHP version 5.1.0 or newer');if(version_compare(PHP_VERSION,'5.0.0','<'))trigger_error('Neevo requires PHP version 5.1.0 or newer',E_USER_ERROR);exit;}class
NeevoConnection{private$neevo,$driver,$username,$password,$host,$database,$encoding,$table_prefix,$resource;public
function
__construct(Neevo$neevo,INeevoDriver$driver,$user,$pswd=null,$host,$database,$encoding=null,$table_prefix=null){$this->neevo=$neevo;$this->driver=$driver;$this->username=$user;$this->password=$pswd;$this->host=$host;$this->database=$database;$this->encoding=$encoding;$this->table_prefix=$table_prefix;$resource=$this->driver()->connect($this->getVars());$this->setResource($resource);}private
function
driver(){return$this->driver;}public
function
getVars(){$options=get_object_vars($this);unset($options['neevo'],$options['driver'],$options['resource']);return$options;}public
function
prefix(){return$this->table_prefix;}public
function
setResource($resource){if(is_resource($resource))$this->resource=$resource;}public
function
resource(){return$this->resource;}public
function
info($hide_password=true){$info=$this->getVars();if($hide_password)$info['password']='*****';$info['driver']=str_replace('NeevoDriver','',get_class($this->driver));return$info;}}class
NeevoQuery{private$table,$type,$limit,$offset,$neevo,$resource,$time,$sql,$performed;private$where,$order,$columns,$data=array();private
static$highlight_colors=array('columns'=>'#00f','chars'=>'#000','keywords'=>'#008000','joins'=>'#555','functions'=>'#008000','constants'=>'#f00');public
static$sql_functions=array('MIN','MAX','SUM','COUNT','AVG','CAST','COALESCE','CHAR_LENGTH','LENGTH','SUBSTRING','DAY','MONTH','YEAR','DATE_FORMAT','CRC32','CURDATE','SYSDATE','NOW','GETDATE','FROM_UNIXTIME','FROM_DAYS','TO_DAYS','HOUR','IFNULL','ISNULL','NVL','NVL2','INET_ATON','INET_NTOA','INSTR','FOUND_ROWS','LAST_INSERT_ID','LCASE','LOWER','UCASE','UPPER','LPAD','RPAD','RTRIM','LTRIM','MD5','MINUTE','ROUND','SECOND','SHA1','STDDEV','STR_TO_DATE','WEEK','RAND');function
__construct(Neevo$object,$type='',$table=''){$this->neevo=$object;$this->type($type);$this->table($table);}public
function
setTime($time){$this->time=$time;}public
function
time(){return$this->time;}public
function
table($table){$this->table=$table;return$this;}public
function
type($type){$this->type=$type;return$this;}public
function
sql($sql){$this->sql=$sql;$this->type('sql');return$this;}public
function
cols($columns){if(!is_array($columns))$columns=explode(',',$columns);$this->columns=$columns;return$this;}public
function
data(array$data){$this->data=$data;return$this;}public
function
performed(){return$this->performed;}public
function
neevo(){return$this->neevo;}public
function
resource(){return$this->resource;}public
function
getTable(){return$this->table;}public
function
getType(){return$this->type;}public
function
getLimit(){return$this->limit;}public
function
getOffset(){return$this->offset;}public
function
getSql(){return$this->sql;}public
function
getWhere(){return$this->where;}public
function
getOrder(){return$this->order;}public
function
getCols(){return$this->columns;}public
function
getData(){return$this->data;}public
function
where($where,$value,$glue=null){$where_condition=explode(' ',$where);if(is_null($value)){$where_condition[1]="IS";$value="NULL";}if(is_array($value))$where_condition[1]="IN";if(!isset($where_condition[1]))$where_condition[1]='=';$column=$where_condition[0];$condition=array($column,$where_condition[1],$value,strtoupper($glue?$glue:"and"));$this->where[]=$condition;return$this;}public
function
order($args){$rules=array();$arguments=func_get_args();foreach($arguments
as$argument){$order_rule=explode(' ',$argument);$rules[]=$order_rule;}$this->order=$rules;return$this;}public
function
limit($limit,$offset=null){$this->limit=$limit;if(isset($offset)&&$this->getType()=='select')$this->offset=$offset;return$this;}public
function
dump($color=true,$return_string=false){$code=$color?self::_highlightSql($this->build()):$this->build();if(!$return_string)echo$code;return$return_string?$code:$this;}public
function
run(){$start=explode(" ",microtime());$query=$this->neevo()->driver()->query($this->build(),$this->neevo()->connection()->resource());if(!$query){$this->neevo()->error('Query failed');return
false;}else{$this->neevo()->incrementQueries();$this->neevo()->setLast($this);$end=explode(" ",microtime());$time=round(max(0,$end[0]-$start[0]+$end[1]-$start[1]),4);$this->setTime($time);$this->performed=true;if(in_array($this->getType(),array('select','sql'))){$this->resource=$query;return$query;}else
return$this;}}public
function
fetch($fetch_type=null){$rows=null;if(!in_array($this->getType(),array('select','sql')))$this->neevo()->error('Cannot fetch on this kind of query');$resource=$this->performed()?$this->resource():$this->run();while($tmp_rows=$this->neevo()->driver()->fetch($resource))$rows[]=(count($tmp_rows)==1)?$tmp_rows[max(array_keys($tmp_rows))]:$tmp_rows;$this->neevo()->driver()->free($resource);if(count($rows)==1&&$fetch_type!=Neevo::MULTIPLE)$rows=$rows[0];if(!count($rows)&&is_array($rows))return
false;return$resource?$rows:$this->neevo()->error("Fetching result data failed");}public
function
seek($row_number){if(!$this->performed())$this->run();$seek=$this->neevo()->driver()->seek($this->resource(),$row_number);return$seek?$seek:$this->neevo()->error("Cannot seek to row $row_number");}public
function
insertId(){if(!$this->performed())$this->run();return$this->neevo()->driver()->insertId($this->neevo()->connection()->resource());}public
function
rand(){$this->neevo()->driver()->rand($this);return$this;}public
function
rows(){if(!$this->performed())$this->run();return$this->neevo()->driver()->rows($this);}public
function
undo($sql_part,$position=1){$str=false;switch(strtolower($sql_part)){case'where':$part='where';break;case'order';$part='order';break;case'column';$part='columns';break;case'value';$part='data';break;case'limit':$part='limit';$str=true;break;case'offset':$part='offset';$str=true;break;default:$this->neevo()->error("Undo failed: No such Query part '$sql_part' supported for undo()");break;}if($str)unset($this->$part);else{if(isset($this->$part)){$positions=array();if(!is_array($position))$positions[]=$position;foreach($positions
as$pos){$pos=is_numeric($pos)?$pos-1:$pos;$apart=$this->$part;unset($apart[$pos]);foreach($apart
as$key=>$value){$loop[$key]=$value;}$this->$part=$loop;}}else$this->neevo()->error("Undo failed: No such Query part '$sql_part' for this kind of Query");}$this->performed=null;$this->resource=null;return$this;}public
function
build(){return$this->neevo()->driver()->build($this);}public
function
info($hide_password=true){$info=array('type'=>$this->getType(),'table'=>$this->getTable(),'executed'=>(bool)$this->performed(),'query-string'=>$this->dump(false,true),'connection'=>$this->neevo()->connection()->info($hide_password));if($this->performed()){$info['time']=$this->time();if($this->getType()=='insert')$info['last-insert-id']=$this->insertId();}return$info;}public
function
getPrimary(){$return=null;$table=$this->neevo()->driver()->buildTablename($this);$q=$this->neevo()->sql('EXPLAIN '.$table);foreach($q->fetch(Neevo::MULTIPLE)as$col){if($col['Key']=='PRI'&&!isset($return))$return=$col['Field'];}return$return;}private
static
function
_highlightSql($sql){$color_codes=array('chars'=>'chars','keywords'=>'kwords','joins'=>'joins','functions'=>'funcs','constants'=>'consts');$colors=self::$highlight_colors;unset($colors['columns']);$words=array('keywords'=>array('SELECT','UPDATE','INSERT','DELETE','REPLACE','INTO','CREATE','ALTER','TABLE','DROP','TRUNCATE','FROM','ADD','CHANGE','COLUMN','KEY','WHERE','ON','CASE','WHEN','THEN','END','ELSE','AS','USING','USE','INDEX','CONSTRAINT','REFERENCES','DUPLICATE','LIMIT','OFFSET','SET','SHOW','STATUS','BETWEEN','AND','IS','NOT','OR','XOR','INTERVAL','TOP','GROUP BY','ORDER BY','DESC','ASC','COLLATE','NAMES','UTF8','DISTINCT','DATABASE','CALC_FOUND_ROWS','SQL_NO_CACHE','MATCH','AGAINST','LIKE','REGEXP','RLIKE','PRIMARY','AUTO_INCREMENT','DEFAULT','IDENTITY','VALUES','PROCEDURE','FUNCTION','TRAN','TRANSACTION','COMMIT','ROLLBACK','SAVEPOINT','TRIGGER','CASCADE','DECLARE','CURSOR','FOR','DEALLOCATE'),'joins'=>array('JOIN','INNER','OUTER','FULL','NATURAL','LEFT','RIGHT'),'functions'=>self::$sql_functions,'chars'=>'/([\\.,!\\(\\)<>:=`]+)/i','constants'=>'/(\'[^\']*\'|[0-9]+)/i');$sql=str_replace('\\\'','\\&#039;',$sql);foreach($color_codes
as$key=>$code){$regexp=in_array($key,array('constants','chars'))?$words[$key]:'/\\b('.join("|",$words[$key]).')\\b/i';$sql=preg_replace($regexp,"<span style=\"color:$code\">$1</span>",$sql);}$sql=str_replace($color_codes,$colors,$sql);return"<code style=\"color:".self::$highlight_colors['columns']."\"> $sql </code>\n";}}class
NeevoDriver{public
function
buildTablename(NeevoQuery$query){$pieces=explode(".",$query->getTable());$prefix=$query->neevo()->connection()->prefix();if(isset($pieces[1]))return$this->col_quotes[0].$pieces[0].$this->col_quotes[1].".".$this->col_quotes[0].$prefix.$pieces[1].$this->col_quotes[1];else
return$this->col_quotes[0].$prefix.$pieces[0].$this->col_quotes[1];}protected
function
buildWhere(NeevoQuery$query){$prefix=$query->neevo()->connection()->prefix();$in_construct=false;foreach($query->getWhere()as$where){if(is_array($where[2])){$where[2]="(".join(", ",$this->_escapeArray($where[2])).")";$in_construct=true;}$wheres[]=$where;}unset($wheres[count($wheres)-1][3]);foreach($wheres
as$in_where){if($this->_isSqlFunc($in_where[0]))$in_where[0]=$this->_quoteSqlFunc($in_where[0]);if(strstr($in_where[0],"."))$in_where[0]=preg_replace("#([0-9A-Za-z_]{1,256})(\.)([0-9A-Za-z_]+)#",$this->col_quotes[0]."$prefix$1".$this->col_quotes[1].".".$this->col_quotes[0]."$3".$this->col_quotes[1],$in_where[0]);else$in_where[0]=$this->col_quotes[0].$in_where[0].$this->col_quotes[1];if(!$in_construct)$in_where[2]=$this->_escapeString($in_where[2]);$wheres2[]=join(' ',$in_where);}return" WHERE ".join(' ',$wheres2);}protected
function
buildInsertData(NeevoQuery$query){foreach($this->_escapeArray($query->getData())as$col=>$value){$cols[]=$this->col_quotes[0].$col.$this->col_quotes[1];$values[]=$value;}return" (".join(', ',$cols).") VALUES (".join(', ',$values).")";}protected
function
buildUpdateData(NeevoQuery$query){foreach($this->_escapeArray($query->getData())as$col=>$value){$update[]=$this->col_quotes[0].$col.$this->col_quotes[1]."=".$value;}return" SET ".join(', ',$update);}protected
function
buildOrder(NeevoQuery$query){foreach($query->getOrder()as$in_order){$in_order[0]=($this->_isSqlFunc($in_order[0]))?$in_order[0]:$this->col_quotes[0].$in_order[0].$this->col_quotes[1];$orders[]=join(' ',$in_order);}return" ORDER BY ".join(', ',$orders);}protected
function
buildSelectCols(NeevoQuery$query){$prefix=$query->neevo()->connection()->prefix();foreach($query->getCols()as$col){$col=trim($col);if($col!='*'){if(strstr($col,".*")){$col=preg_replace("#([0-9A-Za-z_]+)(\.)(\*)#",$this->col_quotes[0]."$prefix$1".$this->col_quotes[1].".*",$col);}else{if(strstr($col,"."))$col=preg_replace("#([0-9A-Za-z_]{1,64})(\.)([0-9A-Za-z_]+)#",$this->col_quotes[0]."$prefix$1".$this->col_quotes[1].".".$this->col_quotes[0]."$3".$this->col_quotes[1],$col);if($this->_isAsConstr($col))$col=$this->_quoteAsConstr($col);elseif($this->_isSqlFunc($col))$col=$this->_quoteSqlFunc($col);elseif(!strstr($col,"."))$col=$this->col_quotes[0].$col.$this->col_quotes[1];}}$cols[]=$col;}return
join(', ',$cols);}protected
function
_escapeArray(array$array){foreach($array
as&$value){$value=is_numeric($value)?$value:(is_string($value)?$this->_escapeString($value):(is_array($value)?$this->_escapeArray($value):$value));}return$array;}protected
function
_escapeString($string){if(get_magic_quotes_gpc())$string=stripslashes($string);$string=$this->escapeString($string);return$this->_isSqlFunc($string)?$this->_quoteSqlFunc($string):"'$string'";}protected
function
_isSqlFunc($string){if(is_string($string)){$var=strtoupper(preg_replace('/[^a-zA-Z0-9_\(\)]/','',$string));return
in_array(preg_replace('/\(.*\)/','',$var),NeevoQuery::$sql_functions);}else
return
false;}protected
function
_quoteSqlFunc($sql_func){return
str_replace(array('("','")'),array('(\'','\')'),$sql_func);}protected
function
_isAsConstr($string){return(bool)preg_match('/(.*) as \w*/i',$string);}protected
function
_quoteAsConstr($as_constr){$col_quote=$this->getQuotes();$construction=explode(' ',$as_constr);$escape=preg_match('/^\w{1,}$/',$construction[0])?true:false;if($escape){$construction[0]=$col_quote[0].$construction[0].$col_quote[1];}$as_constr=join(' ',$construction);return
preg_replace('/(.*) (as) (\w*)/i','$1 AS '.$col_quote[0].'$3'.$col_quote[1],$as_constr);}}interface
INeevoDriver{public
function
__construct(Neevo$neevo);public
function
connect(array$opts);public
function
close($resource);public
function
free($result);public
function
query($query_string,$resource);public
function
error($neevo_msg);public
function
fetch($resource);public
function
seek($resource,$row_number);public
function
insertId($resource);public
function
rand(NeevoQuery$query);public
function
rows(NeevoQuery$query);public
function
build(NeevoQuery$query);public
function
escapeString($string);public
function
getQuotes();public
function
neevo();}class
NeevoDriverMySQL
extends
NeevoDriver
implements
INeevoDriver{protected$col_quotes=array('`','`');private$neevo;public
function
__construct(Neevo$neevo){if(!extension_loaded("mysql"))throw
new
NeevoException("PHP extension 'mysql' not loaded.");$this->neevo=$neevo;}public
function
connect(array$opts){$connection=@mysql_connect($opts['host'],$opts['username'],$opts['password']);if(!is_resource($connection))$this->neevo()->error("Connection to host '".$opts['host']."' failed");if($opts['database']){$db=mysql_select_db($opts['database']);if(!$db)$this->neevo()->error("Could not select database '{$opts['database']}'");}if($opts['encoding']&&is_resource($connection)){if(function_exists('mysql_set_charset'))$ok=@mysql_set_charset($opts['encoding'],$connection);if(!$ok)$this->neevo()->sql("SET NAMES ".$opts['encoding'])->run();}return$connection;}public
function
close($resource){@mysql_close($resource);}public
function
free($result){return@mysql_free_result($result);}public
function
query($query_string,$resource){return@mysql_query($query_string,$resource);}public
function
error($neevo_msg){$mysql_msg=mysql_error();$mysql_msg=str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use','Syntax error',$mysql_msg);$msg=$neevo_msg.".";if($mysql_msg)$msg.=" ".$mysql_msg;return$msg;}public
function
fetch($resource){return@mysql_fetch_assoc($resource);}public
function
seek($resource,$row_number){return@mysql_data_seek($resource,$row_number);}public
function
insertId($resource){return
mysql_insert_id($resource);}public
function
rand(NeevoQuery$query){$query->order('RAND()');}public
function
rows(NeevoQuery$query){if($query->getType()!='select')$aff_rows=$query->performed()?@mysql_affected_rows($query->neevo()->connection()->resource()):false;else$num_rows=@mysql_num_rows($query->resource());if($num_rows||$aff_rows)return$num_rows?$num_rows:$aff_rows;else
return
false;}public
function
build(NeevoQuery$query){$where="";$order="";$limit="";$q="";if($query->getSql())$q=$query->getSql();else{$table=$this->buildTablename($query);if($query->getWhere())$where=$this->buildWhere($query);if($query->getOrder())$order=$this->buildOrder($query);if($query->getLimit())$limit=" LIMIT ".$query->getLimit();if($query->getOffset())$limit.=" OFFSET ".$query->getOffset();if($query->getType()=='select'){$cols=$this->buildSelectCols($query);$q.="SELECT $cols FROM $table$where$order$limit";}if($query->getType()=='insert'&&$query->getData()){$insert_data=$this->buildInsertData($query);$q.="INSERT INTO $table$insert_data";}if($query->getType()=='update'&&$query->getData()){$update_data=$this->buildUpdateData($query);$q.="UPDATE $table$update_data$where$order$limit";}if($query->getType()=='delete')$q.="DELETE FROM $table$where$order$limit";}return"$q;";}public
function
escapeString($string){return
mysql_real_escape_string($string);}public
function
getQuotes(){return$this->col_quotes;}public
function
neevo(){return$this->neevo;}}class
Neevo{private$connection,$last,$queries,$error_reporting,$driver,$error_handler;const
E_NONE=11;const
E_HANDLE=12;const
E_STRICT=13;const
VERSION="0.3dev";const
REVISION=120;const
MULTIPLE=21;public
function
__construct($driver){if(!$driver)throw
new
NeevoException("Driver not defined.");$this->setDriver($driver);}public
function
__destruct(){$this->driver()->close($this->connection()->resource());}public
function
connect(array$opts){$connection=$this->createConnection($opts);$this->setConnection($connection);return(bool)$connection;}public
function
connection(){return$this->connection;}public
function
createConnection(array$opts){return
new
NeevoConnection($this,$this->driver(),$opts['username'],$opts['password'],$opts['host'],$opts['database'],$opts['encoding'],$opts['table_prefix']);}public
function
useConnection(NeevoConnection$connection){$this->setConnection($connection);return$this;}private
function
setConnection(NeevoConnection$connection){$this->connection=$connection;}private
function
setDriver($driver){$class="NeevoDriver$driver";if(!$this->isDriver($class)){@include_once
dirname(__FILE__).'/neevo/drivers/'.strtolower($driver).'.php';if(!$this->isDriver($class))throw
new
NeevoException("Unable to create instance of Neevo driver '$driver' - corresponding class not found or not matching criteria.");}$this->driver=new$class($this);}public
function
useDriver($driver){$this->setDriver($driver);return$this;}public
function
driver(){return$this->driver;}private
function
isDriver($class){return(class_exists($class,false)&&in_array("INeevoDriver",class_implements($class,false))&&in_array("NeevoDriver",class_parents($class,false)));}public
function
setLast(NeevoQuery$last){$this->last=$last;}public
function
last(){return$this->last;}public
function
incrementQueries(){$this->queries++;}public
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
setErrorReporting($value){$this->error_reporting=$value;if(!isset($this->error_reporting))$this->error_reporting=self::E_HANDLE;}public
function
errorReporting(){if(!isset($this->error_reporting))$this->error_reporting=self::E_WARNING;return$this->error_reporting;}public
function
setErrorHandler($handler_function){if(function_exists($handler_function))$this->error_handler=$handler_function;else$this->error_handler=array('Neevo','defaultErrorHandler');}public
function
errorHandler(){$func=$this->error_handler;if((is_array($func)&&!method_exists($func[0],$func[1]))||(!is_array($func)&&!function_exists($func)))$this->error_handler=array('Neevo','defaultErrorHandler');return$this->error_handler;}public
function
error($neevo_msg){$level=$this->errorReporting();if($level!=Neevo::E_NONE){$msg=$this->driver()->error($neevo_msg);$exception=new
NeevoException($msg);if($level==Neevo::E_HANDLE)call_user_func($this->errorHandler(),$exception);if($level==Neevo::E_STRICT)throw$exception;}return
false;}public
static
function
defaultErrorHandler(NeevoException$exception){$message=$exception->getMessage();$trace=$exception->getTrace();if(!empty($trace)){$last=$trace[count($trace)-1];$line=$last['line'];$path=$last['file'];$act="occured";}else{$line=$exception->getLine();$path=$exception->getFile();$act="thrown";}$file=basename($path);$path=str_replace($file,"<strong>$file</strong>",$path);echo"<p><strong>Neevo exception</strong> $act in <em>$path</em> on <strong>line $line</strong>: $message</p>\n";}public
function
version($string=true){if($string)$return="Neevo ".self::VERSION." (revision ".self::REVISION.").";else$return=array('version'=>self::VERSION,'revision'=>self::REVISION);return$return;}}class
NeevoException
extends
Exception{};