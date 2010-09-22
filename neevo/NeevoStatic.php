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
 */

/**
 * Main Neevo class for some additional static methods
 * @package Neevo
 * @access private
 */
class NeevoStatic {

  private static $highlight_colors = array(
    'columns'    => '#00f',
    'chars'      => '#000',
    'keywords'   => '#008000',
    'joins'      => '#555',
    'functions'  => '#008000',
    'constants'  => '#f00'
    );

  private static $sql_functions=array('MIN', 'MAX', 'SUM', 'COUNT', 'AVG', 'CAST', 'COALESCE', 'CHAR_LENGTH', 'LENGTH', 'SUBSTRING', 'DAY', 'MONTH', 'YEAR', 'DATE_FORMAT', 'CRC32', 'CURDATE', 'SYSDATE', 'NOW', 'GETDATE', 'FROM_UNIXTIME', 'FROM_DAYS', 'TO_DAYS', 'HOUR', 'IFNULL', 'ISNULL', 'NVL', 'NVL2', 'INET_ATON', 'INET_NTOA', 'INSTR', 'FOUND_ROWS', 'LAST_INSERT_ID', 'LCASE', 'LOWER', 'UCASE', 'UPPER', 'LPAD', 'RPAD', 'RTRIM', 'LTRIM', 'MD5', 'MINUTE', 'ROUND', 'SECOND', 'SHA1', 'STDDEV', 'STR_TO_DATE', 'WEEK', 'RAND');

  /** Highlights given MySQL query */
  public static function highlight_sql($sql){
    $color_codes = array('chars'=>'chars','keywords'=>'kwords','joins'=>'jonis','functions'=>'funcs','constants'=>'consts');
    $colors = self::$highlight_colors;
    unset($colors['columns']);

    $words = array(
      'keywords'  => array('SELECT', 'UPDATE', 'INSERT', 'DELETE', 'REPLACE', 'INTO', 'CREATE', 'ALTER', 'TABLE', 'DROP', 'TRUNCATE', 'FROM', 'ADD', 'CHANGE', 'COLUMN', 'KEY', 'WHERE', 'ON', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'AS', 'USING', 'USE', 'INDEX', 'CONSTRAINT', 'REFERENCES', 'DUPLICATE', 'LIMIT', 'OFFSET', 'SET', 'SHOW', 'STATUS', 'BETWEEN', 'AND', 'IS', 'NOT', 'OR', 'XOR', 'INTERVAL', 'TOP', 'GROUP BY', 'ORDER BY', 'DESC', 'ASC', 'COLLATE', 'NAMES', 'UTF8', 'DISTINCT', 'DATABASE', 'CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'MATCH', 'AGAINST', 'LIKE', 'REGEXP', 'RLIKE', 'PRIMARY', 'AUTO_INCREMENT', 'DEFAULT', 'IDENTITY', 'VALUES', 'PROCEDURE', 'FUNCTION', 'TRAN', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRIGGER', 'CASCADE', 'DECLARE', 'CURSOR', 'FOR', 'DEALLOCATE'),
      'joins'     => array('JOIN', 'INNER', 'OUTER', 'FULL', 'NATURAL', 'LEFT', 'RIGHT'),
      'functions' => self::$sql_functions,
      'chars'     => '/([\\.,!\\(\\)<>:=`]+)/i',
      'constants' => '/(\'[^\']*\'|[0-9]+)/i'
    );

    $sql=str_replace('\\\'','\\&#039;', $sql);

    foreach($color_codes as $key => $code){
      $regexp = in_array( $key, array('constants', 'chars')) ? $words[$key] : '/\\b(' .join("|", $words[$key]) .')\\b/i';
      $sql = preg_replace($regexp, "<span style=\"color:$code\">$1</span>", $sql);
    }

    $sql = str_replace($color_codes, $colors, $sql);
    return "<code style=\"color:".self::$highlight_colors['columns']."\"> $sql </code>\n";
  }

  /** Escapes whole array for use in SQL */
  public static function escape_array(array $array, Neevo $neevo){
    foreach($array as &$value){
       $value = is_numeric($value) ? $value : ( is_string($value) ? self::escape_string($value, $neevo) : ( is_array($value) ? self::escape_array($value) : $value ) );
    }
    return $array;
  }

  /** Escapes given string for use in SQL */
  public static function escape_string($string, Neevo $neevo){
    if(get_magic_quotes_gpc()) $string = stripslashes($string);
    $string = $neevo->driver()->escape_string($string);
    return self::is_sql_func($string) ? self::quote_sql_func($string) : "'$string'";
  }

  /** Checks whether a given string is a SQL function or not */
  public static function is_sql_func($string){
    if(is_string($string)){
      $var = strtoupper(preg_replace('/[^a-zA-Z0-9_\(\)]/', '', $string));
      return in_array( preg_replace('/\(.*\)/', '', $var), self::$sql_functions);
    }
    else return false;

  }

  /** Quotes given SQL function  */
  public static function quote_sql_func($sql_func){
    return str_replace(array('("', '")'), array('(\'', '\')'), $sql_func);
  }

  /** Checks whether a given string is a MySQL 'AS construction' ([SELECT] fruit AS vegetable) */
  public static function is_as_constr($string){
    return (bool) preg_match('/(.*) as \w*/i', $string);
  }

  /** Quotes given 'AS construction' */
  public static function quote_as_constr($as_constr, array $col_quote){
    $construction = explode(' ', $as_constr);
    $escape = preg_match('/^\w{1,}$/', $construction[0]) ? true : false;
    if($escape){
      $construction[0] = $col_quote[0] .$construction[0] .$col_quote[1];
    }
    $as_constr = join(' ', $construction);
    return preg_replace('/(.*) (as) (\w*)/i','$1 AS ' .$col_quote[0] .'$3' .$col_quote[1], $as_constr);
  }

  /** Returns formatted filesize */
  public static function filesize($bytes){
    $unit = array('B','kB','MB','GB','TB','PB');
    return @round($bytes/pow(1024, ($i = floor(log($bytes, 1024)))), 2).' '.$unit[$i];
  }

}
?>