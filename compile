#!/usr/bin/php
<?php
if(!$_SERVER['SHELL'])
  trigger_error("This script should be run from CLI (command-line interface) only.", E_USER_ERROR);


// Defaults
define("DEFAULT_FILE", "neevo.php");
define("LAST_INCLUDE", "include_once dirname(__FILE__). '/neevo/INeevoDriver.php';");
define("DRIVERS_PATH", "/neevo/drivers/");

$args = getopt('hrmqf::');

$nooutput = isset($args['q']);

if(empty($args)){
  out("\nError: No arguments passed.\n\n");
  help();
  exit;
}



/* ******* Actions ******* */


// -h: Help
if(isset($args['h'])){
  help();
  exit;
}

out("\n");

// -f=<filename>: Set <filename>
if($args['f'] !== false)
  $file = str_replace('=', '', $args['f']); // PHP < 5.3 compatibility

if(!file_exists($file)){
  if($file !== '')
    out("File '$file' doesn't exist, default will be used\n");
  $file = DEFAULT_FILE;
}
if(file_exists($file))
  out("Using file '$file'\n");


// -r: Revision number
if(isset($args['r']))
  out(revision($file));

// -m: Minify source
if(isset($args['m']))
  out(minify($file));


out("\n");




/* ******* Functions ******* */



function revision($file){
  $source = file_get_contents($file);
  global $new_rev;
  $newsource = preg_replace_callback("#const REVISION = (\d+);#", "revision_callback", $source);
  $x = file_put_contents($file, $newsource);
  $response = $x ? "Success: Revision number changed to $new_rev" : "Error: Revision number change failed";
  return "$response\n";
}


function revision_callback($n){
  global $new_rev;
  $res = $n[1]+1;
  $new_rev = $res;
  return "const REVISION = $res;";
}


function drivers($file){
  $content = str_replace("
      @include_once dirname(__FILE__) . '/neevo/drivers/'.strtolower(\$driver).'.php';

      if(!\$this->is_driver(\$class))
  ", "\n", file_get_contents($file));

  $content = str_replace(LAST_INCLUDE, list_drivers(), $content);
  return $content;
}


function list_drivers(){
  $pattern = glob(".".DRIVERS_PATH."*.php");
  sort($pattern);
  foreach($pattern as $filename){
    $list .= "\ninclude_once dirname(__FILE__). '".DRIVERS_PATH.basename($filename)."';";
  }
  return LAST_INCLUDE.$list;
}


function out($string){
  global $nooutput;
  if($nooutput === false)
    echo $string;
}



function help(){
out("Usage:
  $ php ".basename(__FILE__)." [options]

Options:

  -f=<filename>  File to compile. Defaults to ".DEFAULT_FILE."
  -r             Increments REVISION in <filename>
  -m             Minifies source code of <filename>
  -h             Displays help
  -q             Script produces no output
");
}


/**
 * Core minify functions (include_file, short_identifier and php_shrink) used in this
 * script are written by Jakub Vrana (http://php.vrana.cz) and extracted from
 * his open-soure "Compact MySQL management" - Adminer (http://adminer.org)
 * released under Apache license 2.0.
 */


function minify($file, $short_variables = false){
  $path = pathinfo($file);
  $result_file = $path['dirname']."/".$path['filename'].".minified.".$path['extension'];
  $file = drivers($file);
  $source = str_replace("include_once dirname(__FILE__). '", "include '.", $file);
  $source = preg_replace_callback("~include '([^']+)';~", 'include_file', $source);
  $source = str_replace(array("<?php", "?>"), "", $source);
  $source = "<?php\n$source\n";
  $result = php_shrink($source, $short_variables);
  $x = file_put_contents($result_file, $result);
  //highlight_string($result);exit;
  $response =  $x ? "Success: Source minified" : "Error: Minification failed";
  return "$response\n";
}

/** @copyright Jakub Vrana, http://php.vrana.cz. Used with permission. */
function include_file($match) {
  $file = file_get_contents($match[1]);
  $token = end(token_get_all($file));
  $php = (is_array($token) && in_array($token[0], array(T_CLOSE_TAG, T_INLINE_HTML)));
  $file = "// FILE = ".basename($match[1]).$file;
  return "?>\n$file" . ($php ? "<?php" : "");
}


/**  @copyright Jakub Vrana, http://php.vrana.cz. Used with permission. */
function short_identifier($number, $chars) {
  $return = '';
  while ($number >= 0) {
    $return .= $chars{$number % strlen($chars)};
    $number = floor($number / strlen($chars)) - 1;
  }
  return $return;
}


/**  @copyright Jakub Vrana, http://php.vrana.cz. Used with permission. */
function php_shrink($input, $shorted) {
  $special_variables = array_flip(array('$this', '$GLOBALS', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_SERVER'));
  $short_variables = array();
  $shortening = $shorted;
  $tokens = token_get_all($input);

  foreach ($tokens as $i => $token) {
    if ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
      $short_variables[$token[1]]++;
    }
  }

  arsort($short_variables);
  foreach (array_keys($short_variables) as $number => $key) {
    $short_variables[$key] = short_identifier($number, implode("", range('a', 'z')) . '_' . implode("", range('A', 'Z'))); // could use also numbers and \x7f-\xff
  }

  $set = array_flip(preg_split('//', '!"#$&\'()*+,-./:;<=>?@[\]^`{|}'));
  $space = '';
  $output = '';
  $in_echo = false;
  $doc_comment = false; // include only first /**
  for (reset($tokens); list($i, $token) = each($tokens); ) {
    if (!is_array($token)) {
      $token = array(0, $token);
    }
    if ($tokens[$i+2][0] === T_CLOSE_TAG && $tokens[$i+3][0] === T_INLINE_HTML && $tokens[$i+4][0] === T_OPEN_TAG
      && strlen(addcslashes($tokens[$i+3][1], "'\\")) < strlen($tokens[$i+3][1]) + 3
    ) {
      $tokens[$i+2] = array(T_ECHO, 'echo');
      $tokens[$i+3] = array(T_CONSTANT_ENCAPSED_STRING, "'" . addcslashes($tokens[$i+3][1], "'\\") . "'");
      $tokens[$i+4] = array(0, ';');
    }
    if ($token[0] == T_COMMENT || $token[0] == T_WHITESPACE || ($token[0] == T_DOC_COMMENT && $doc_comment)) {
      $space = "\n";
    } else {
      if ($token[0] == T_DOC_COMMENT) {
        $doc_comment = true;
      }
      if ($token[0] == T_VAR) {
        $shortening = false;
      } elseif (!$shortening) {
        if ($token[1] == ';') {
          $shortening = $shorted;
        }
      } elseif ($token[0] == T_ECHO) {
        $in_echo = true;
      } elseif ($token[1] == ';' && $in_echo) {
        if ($tokens[$i+1][0] === T_WHITESPACE && $tokens[$i+2][0] === T_ECHO) {
          next($tokens);
          $i++;
        }
        if ($tokens[$i+1][0] === T_ECHO) {
          // join two consecutive echos
          next($tokens);
          $token[1] = ','; // '.' would conflict with "a".1+2 and would use more memory //! remove ',' and "," but not $var","
        } else {
          $in_echo = false;
        }
      } elseif ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
        $token[1] = '$' . $short_variables[$token[1]];
      }
      if (isset($set[substr($output, -1)]) || isset($set[$token[1]{0}])) {
        $space = '';
      }
      $output .= $space . $token[1];
      $space = '';
    }
  }
  return $output;
}
