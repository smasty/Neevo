<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://neevo.smasty.net/license  MIT license
 * @link     http://neevo.smasty.net/
 *
 */

if(PHP_SAPI !== 'cli'){
  trigger_error("This script should be run from CLI (command-line interface) only.", E_USER_ERROR);
}

$args = getopt('d:hqr');

// Quiet mode
if(isset($args['q'])){
  ob_start();
}

// Help
if(isset($args['h'])){
  Compiler::getHelp();
}

// Retrieve drivers
$drivers = null;
if(isset($args['d'])){
  $drivers = array_map('strtolower', explode(',', $args['d']));
}

$compiler = new Compiler(array(
  'file' => 'neevo.php',
  'includeLine' => '@set_magic_quotes_runtime(FALSE);',
  'drivers' => $drivers
));


// Revision change
if(isset($args['r'])){
  echo $compiler->revision();
}

echo $compiler->minify(), "\n";


// Quiet mode
if(isset($args['q'])){
  ob_end_clean();
}



/**
 * Compiler class
 * @author Martin Srank
 */
class Compiler{

  private $file, $includeLine, $drivers, $allDrivers = true;
  private static $newRevision;

  const BASE_PATH = './neevo';
  const DRIVER_PATH = './neevo/drivers';


  public function  __construct(array $options){
    $this->file = self::BASE_PATH . '/../' .$options['file'];
    $this->includeLine = $options['includeLine'];
    $this->drivers = $this->getDrivers($options['drivers']);
  }

  /**
   * @param array|null
   * @return array
   */
  private function getDrivers($input = null){
    if($input === null){
      return glob(self::DRIVER_PATH . '/*.php');
    }
    foreach($input as $key => $name){
      $path = self::DRIVER_PATH . "/$name.php";
      if(file_exists($path)){
        $drivers[$key] = $path;
      }
    }
    sort($drivers);
    $this->allDrivers = false;
    return $drivers;
  }

  /**
   * @return string Message
   */
  public function revision(){
    $source = @file_get_contents($this->file);

    $newsource = preg_replace_callback('~const REVISION = (\d+);~', array(__CLASS__, 'revisionCallback'), $source);

    $response = @file_put_contents($this->file, $newsource) ?
      "Revision changed to ".self::$newRevision : $this->error('Revision change failed');

    return "$response\n";
  }

  public static function revisionCallback($match){
    self::$newRevision = $match[1]+1;
    return 'const REVISION = ' . self::$newRevision . ';';
  }

  /**
   * @return string Message
   */
  public function minify(){
    $path = pathinfo($this->file);
    // Generate name for minified file
    $filename = $path['dirname'].'/'.$path['filename'].$this->driversToPath().'.min.'.$path['extension'];
    
    //Include files
    $files = array_merge(glob(self::BASE_PATH . '/*.php'), $this->drivers);
    $includes = array();
    foreach($files as $file){
      $includes[] = $this->includeFile($file);
    }

    $source = @file_get_contents($this->file);
    $source = str_replace($this->includeLine, $this->includeLine . join(" ", $includes), $source);

    // Remove all <?php tags
    $source = str_replace(array('<?php', '?>'), '', $source);
    $source = "<?php\n$source\n";

    // Minify file
    $source = $this->phpShrink($source);
    $source = $this->addLicense($source);

    return  @file_put_contents($filename, $source) ?
      'Source minified' : $this->error('Minification failed');
  }

  /**
   * @param string
   * @return string
   */
  private function addLicense($content){
    $last_line = " * @link     http://neevo.smasty.net/\n";
    $license = $last_line . " *\n * The MIT license:\n *\n";

    foreach(file('license.txt') as $line){
      $license .= ' * '. $line;
    }
    return str_replace($last_line, "$license\n", $content);
  }

  /**
   * @return string
   */
  private function driversToPath(){
    if($this->allDrivers){
      return '';
    }
    $drivers = array();
    foreach($this->drivers as $d){
      $drivers[] = substr(basename($d), 0, -4);
    }
    return '.'. join('-', $drivers);
  }

  /**
   * @copyright Jakub Vrana, http://php.vrana.cz. Used with permission.
   * @param string $path
   * @return string
   */
  private function includeFile($path) {
    $file = @file_get_contents($path);
    $token = end(token_get_all($file));
    $php = (is_array($token) && in_array($token[0], array(T_CLOSE_TAG, T_INLINE_HTML)));
    return "?>\n$file" . ($php ? "<?php" : "");
  }

  /**
   * @copyright Jakub Vrana, http://php.vrana.cz. Used with permission.
   * @param string $input
   * @return string
   */
  private function phpShrink($input){
    $tokens = token_get_all($input);

    $set = array_flip(preg_split('//', '!"#$&\'()*+,-./:;<=>?@[\]^`{|}'));
    $space = '';
    $output = '';
    $in_echo = false;
    $doc_comment = false; // include only first /**
    //for(reset($tokens); list($i, $token) = each($tokens); ){
    foreach($tokens as $i => $token){
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
            $token[1] = ','; // '.' would conflict with "a".1+2 and would use more memory
                             //! remove ',' and "," but not $var","
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

  public static function getHelp(){
    echo "Usage:
  $ php ".basename(__FILE__)." [-d <drivers>] [-r] [-h] [-q]

Options:

  -d <drivers>  Comma-separated list of drivers to include -
                defaults to all drivers.
  -r            Increments revision number.
  -h            Displays help.
  -q            Quiet mode - no output.
";
    exit(0);
  }

  private function error($message){
    fwrite(STDERR, "Error: $message\n");
    exit(1);
  }

}