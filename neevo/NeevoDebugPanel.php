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

if(interface_exists('Nette\IDebugPanel')){
  class_alias('Nette\IDebugPanel', 'IDebugPanel');
}

if(interface_exists('IDebugPanel')):

/**
 * Neevo Debug panel - integration with Nette Framework (http://nette.org)
 * @author Martin Srank
 * @package Neevo
 */
class NeevoDebugPanel implements IDebugPanel, SplObserver{

  private $total, $neevo, $queries = array();

  /**
   * Implementation of SplObserver
   * @param Neevo $subject
   */
  public function update(SplSubject $subject){
    // Add Debug Panel
    if(is_callable('Nette\Debug::addPanel')){
      call_user_func('Nette\Debug::addPanel', $this);
    }
    elseif(is_callable('NDebug::addPanel')){
      NDebug::addPanel($this);
    }
    elseif(is_callable('Debug::addPanel')){
      Debug::addPanel($this);
    }
    else throw new NeevoException('Cannot find Nette Debug class.');

    $last = $subject->last();
    $this->queries[] = array($last['sql'], $last['time'], isset($last['rows']) ? $last['rows'] : null);
    $this->total += $last['time'];
    $this->neevo = $subject;
  }

  public function getId(){
    return 'neevo';
  }

  public function getTab(){
    $count = count($this->queries);
    return '<span title="Neevo database layer (rev. #'.$this->neevo->revision().')">'
          .'<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAFmSURBVBgZBcHPa85xAAfw1/Psy9bEFE+MgwyzrLS4KqUQRauVODohB/+As7OzGilZrpQki4OLgyiEImFWmqb5sfZ4vt/P2+vVitn+nMyZMpZdKeV1PpTpMjvZALQe7clMZ+9mawyKJb99sfA0p6e+AR4+/pySJEmSJOnlRe7cjIhoZ3wTAICtyjGAqojvBvRbJZYt+maHAqAqovLTiqj90lWJAqCK6DOgUumpBTPqDkBVRK2n1tJ477tRI+LKoe71pQdXz7eLaNRqjcaCA2LEqLHZY9uac8cHqyJ6ehp9Gpux5LEB+zSGbtxfbhdFrdaIuzYa9spFnYW3y1tMnL2QdmNRRz/4a1HXBvN60vttzry+qTdfJ9urh3WsM+GHrvWe5V/G1zXuTy8cbsWt7eVymWoPDaq9c9Anu634aMS0uaoVwLW19c66PL/05+zQif33fnh5unt7+dGToyIiIiIiTuVIIiL+A271xrBxnHZ+AAAAAElFTkSuQmCC">'
          .($count ? $count : 'No') . ' queries'
          .($this->total ? ' / ' . sprintf('%0.1f', $this->total * 1000) . ' ms' : '')
          .'</span>';
  }

  public function getPanel(){
    if(empty($this->queries)){
      return;
    }
    $qs = '';
    foreach($this->queries as $query){
      list($sql, $time, $rows) = $query;
      $qs .= '<tr><td>'. sprintf('%0.1f', $time * 1000) .'</td>'
          .'<td class="sql">'. self::formatSql($sql) .'</td>'
          .'<td>'. ($rows !== null ? $rows : '-') .'</td></tr>';
    }

    return '<style> #nette-debug-neevo td.sql { background: white !important } </style>'
          .'<h1 style="padding-right:2em">Queries: ' . count($this->queries)
          .($this->total ? ', time: ' . sprintf('%0.1f', $this->total * 1000) . ' ms' : '')
          .'</h1><table><tr><th>Time</th><th>SQL</th><th>Rows</th></tr>'
          .$qs.'</table>'
          .'<p style="margin:1em 0 0;font-size:1ex"><a href="http://neevo.smasty.net" target="_blank">Neevo  – Tiny database layer</a></p>';
  }

  private static function formatSql($sql, $len = 100){
    if(strlen($sql) > $len){
      $sql = substr($sql, 0, $len) . "\xE2\x80\xA6";
    }
    return Neevo::highlightSql($sql);
  }


}

endif;