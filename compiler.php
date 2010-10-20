<?php
if(!$_SERVER['SHELL'])
  trigger_error("This script should be run from CLI (command-line interface) only.", E_USER_ERROR);

// Defaults
$file = 'neevo.php';
$last_include_line = "include_once dirname(__FILE__). '/neevo/INeevoDriver.php';";


$args = getopt('d::hqr');

$nooutput = isset($args['q']);



/* ******* Actions ******* */


// Help
if(isset($args['h'])){
  out("Usage:
  $ php ".basename(__FILE__)." [-d=<drivers>] -h

Options:

  -d=<drivers>  Comma-separated list of drivers to include. Defaults to all drivers.
  -h            Displays help.
");

  exit;
}


// Revision number
if(isset($args['r']))
  out(revision($file));


// Include only few drivers
if(isset($args['d'])){
  $drivers = explode(',', str_replace('=', '', $args['d'])); // PHP < 5.3 compatibility

  foreach($drivers as &$d)
    $d = strtolower(trim($d));
}
// All drivers
else $drivers = null;


// Minify source
out(minify($file, $drivers));

out("\n");


/* ******* Functions ******* */


function revision($file){
  $source = file_get_contents($file);
  global $new_rev;

  $newsource = preg_replace_callback("#const REVISION = (\d+);#", "revision_callback", $source);
  
  $response = file_put_contents($file, $newsource) ?
    "Revision changed to $new_rev" : "Error: Revision change failed";

  return "$response\n";
}


function revision_callback($n){
  global $new_rev;
  $res = $n[1]+1;
  $new_rev = $res;
  return "const REVISION = $res;";
}


function drivers($file){
  global $last_include_line;

  // Remove driver autoload from Neevo class
  $content = str_replace("
      @include_once dirname(__FILE__) . '/neevo/drivers/'.strtolower(\$driver).'.php';

      if(!\$this->isDriver(\$class))
  ", "\n", @file_get_contents($file));

  $content = str_replace($last_include_line, list_drivers(), $content);
  return $content;
}


function list_drivers(){
  global $drivers, $last_include_line;

  // Include all
  if($drivers === null)
    $pattern = glob('./neevo/drivers/*.php');
  
  // Include only defined
  elseif(is_array($drivers)){
    $pattern = array();
    foreach($drivers as $driver)
      $pattern[] = './neevo/drivers/'.$driver.'.php';

    out('Compiling drivers: '.join(', ', $drivers)."\n");
  }

  // Sort drivers alphabeticaly
  sort($pattern);

  // Create include statements
  foreach($pattern as $filename){
    $list .= "\ninclude_once dirname(__FILE__). '/neevo/drivers/".basename($filename)."';";
  }
  return $last_include_line.$list;
}


function minify($file){
  $path = pathinfo($file);
  // Create name for minified file
  $result_file = $path['dirname']."/".$path['filename'].".min.".$path['extension'];

  // Create include statements for drivers
  $file = drivers($file);

  // Join all included files together
  $source = str_replace("include_once dirname(__FILE__). '", "include '.", $file);
  $source = preg_replace_callback("~include '([^']+)';~", 'include_file', $source);

  // Remove all <?php tags
  $source = str_replace(array("<?php", "?>"), "", $source);
  $source = "<?php\n$source\n";

  // Minify file
  $result = php_shrink($source);

  // Save to file
  return  file_put_contents($result_file, $result) ?
    "Source minified" : "Error: Minification failed";
}


/** @copyright Jakub Vrana, http://php.vrana.cz. Used with permission. */
function include_file($match) {
  $file = @file_get_contents($match[1]);
  $token = end(token_get_all($file));
  $php = (is_array($token) && in_array($token[0], array(T_CLOSE_TAG, T_INLINE_HTML)));
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
function php_shrink($input){
  $tokens = token_get_all($input);

  $set = array_flip(preg_split('//', '!"#$&\'()*+,-./:;<=>?@[\]^`{|}'));
  $space = '';
  $output = '';
  $in_echo = false;
  $doc_comment = false; // include only first /**
  for (reset($tokens); list($i, $token) = each($tokens); ){
    if(!is_array($token)){
      $token = array(0, $token);
    }
    if($tokens[$i+2][0] === T_CLOSE_TAG && $tokens[$i+3][0] === T_INLINE_HTML && $tokens[$i+4][0] === T_OPEN_TAG
      && strlen(addcslashes($tokens[$i+3][1], "'\\")) < strlen($tokens[$i+3][1]) + 3
    ){
      $tokens[$i+2] = array(T_ECHO, 'echo');
      $tokens[$i+3] = array(T_CONSTANT_ENCAPSED_STRING, "'" . addcslashes($tokens[$i+3][1], "'\\") . "'");
      $tokens[$i+4] = array(0, ';');
    }
    if($token[0] == T_COMMENT || $token[0] == T_WHITESPACE || ($token[0] == T_DOC_COMMENT && $doc_comment)){
      $space = "\n";
    }
    else{
      if($token[0] == T_DOC_COMMENT){
        $doc_comment = true;
      }
      if($token[0] == T_ECHO){
        $in_echo = true;
      }
      elseif($token[1] == ';' && $in_echo){
        if($tokens[$i+1][0] === T_WHITESPACE && $tokens[$i+2][0] === T_ECHO){
          next($tokens);
          $i++;
        }
        if($tokens[$i+1][0] === T_ECHO){
          // join two consecutive echos
          next($tokens);
          $token[1] = ','; // '.' would conflict with "a".1+2 and would use more memory //! remove ',' and "," but not $var","
        }
        else{
          $in_echo = false;
        }
      }
      if(isset($set[substr($output, -1)]) || isset($set[$token[1]{0}])){
        $space = '';
      }
      $output .= $space . $token[1];
      $space = '';
    }
  }
  return $output;
}


function out($string){
  global $nooutput;
  if($nooutput === false)
    echo $string;
}