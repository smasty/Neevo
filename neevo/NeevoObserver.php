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
 * @license  http://neevo.smasty.net/license MIT license
 * @link     http://neevo.smasty.net/
 *
 */


/**
 * Neevo observer interface.
 * @author Martin Srank
 * @package Neevo
 */
interface INeevoObserver {

    // Event types
    const CONNECT = 2;

    const SELECT = 4;
    const INSERT = 8;
    const UPDATE = 16;
    const DELETE = 32;
    const QUERY = 60; // SELECT/INSERT/UPDATE/DELETE

    const BEGIN = 64;
    const COMMIT = 128;
    const ROLLBACK = 256;
    const TRANSACTION = 448; // BEGIN/COMMIT/ROLLBACK

    const EXCEPTION = 512;
    const ALL = 1022;


    /**
     * Receive update from observable.
     * @param INeevoObservable $observable
     * @param int $event Event type (use INeevoObserver constants)
     * @param NeevoStmtBase $statement Optional statement instance
     * @return void
     */
    public function updateStatus(INeevoObservable $observable, $event, NeevoStmtBase $statement = null);


}



/**
 * Neevo observable interface.
 * @author Martin Srank
 * @package Neevo
 */
interface INeevoObservable {


    /**
     * Attach given observer.
     * @param INeevoObserver $observer
     * @return void
     */
    public function attachObserver(INeevoObserver $observer);


    /**
     * Detach given observer.
     * @param INeevoObserver $observer
     * @return void
     */
    public function detachObserver(INeevoObserver $observer);


    /**
     * Notify all attached observers.
     * @param int $event
     * @param NeevoStmtBase $statement
     * @return void
     */
    public function notifyObservers($event, NeevoStmtBase $statement = null);


}



/**
 * Basic implementation of INeevoObserver.
 * @package Neevo
 * @author Martin Srank
 */
class NeevoObserver implements INeevoObserver, IDebugPanel {


    /** @var array Event type conversion table */
    private static $eventTable = array(
        INeevoObserver::CONNECT => 'Connect',
        INeevoObserver::SELECT => 'SELECT',
        INeevoObserver::INSERT => 'INSERT',
        INeevoObserver::UPDATE => 'UPDATE',
        INeevoObserver::DELETE => 'DELETE',
        INeevoObserver::BEGIN => 'BEGIN',
        INeevoObserver::COMMIT => 'COMMIT',
        INeevoObserver::ROLLBACK => 'ROLLBACK',
        INeevoObserver::EXCEPTION => 'ERROR'
    );

    /** @var string */
    private $file;

    /** @var float */
    private $totalTime;

    /** @var int */
    private $numQueries;

    /** @var array */
    private $tickets = array();

    /** @var int */
    private $filter = INeevoObserver::ALL;


    /**
     * Create the observer.
     *
     * Available config:
     * - filter (int) => Event type filter (see constants).
     * - file => path to log file if you want to log events to file.
     * @param array|Traversable $config
     * @return void
     */
    public function __construct($config = null){

        if($config !== null){
            $config = ($config instanceof Traversable ? iterator_to_array($config) : $config);

            if(!is_array($config))
                throw new InvalidArgumentException('Configuration must be an array or instance of Traversable.');

            if(isset($config['file'])){
                $this->file = $config['file'];
            }

            if(isset($config['filter'])){
                $this->filter = (int) $config['filter'];
            }
        }

        // Try register Nette\Debug Panel
        if(is_callable('Nette\Debug::addPanel')){
            call_user_func('Nette\Debug::addPanel', $this);
        }
        elseif(is_callable('NDebug::addPanel')){
            NDebug::addPanel($this);
        }
        elseif(is_callable('Debug::addPanel')){
            Debug::addPanel($this);
        }
    }


    /**
     * Receive update from observable.
     * @param INeevoObservable $observable
     * @param int $event Event type (use INeevoObserver constants)
     * @param NeevoStmtBase $statement Optional statement instance
     * @return void
     */
    public function updateStatus(INeevoObservable $observable, $event, NeevoStmtBase $statement = null){

        if($event & $this->filter){

            if($event & INeevoObserver::QUERY){
                $this->numQueries++;
                $this->totalTime += $statement->time();
            }

            if($statement instanceof NeevoResult){
                try{
                    $rows = count($statement);
                } catch(Exception $e){
                    $rows = '?';
                }
            }
            else $rows = '-';

            $this->tickets[] = $ticket = array($observable, $event, $statement instanceof NeevoStmtBase
                ? array((string) $statement, $statement->time(), $rows) : null);

            if(isset($this->file)){
                $this->logFile($ticket);
            }
        }
    }


    /**
     * Log the ticket to file.
     * @param array $ticket
     * @return void
     */
    private function logFile($ticket){
        list($o, $event, $stmt) = $ticket;

        $s = date('Y-m-d H:i:s: ') . self::$eventTable[$event];

        if($o instanceof NeevoException){
            $s .= "\n--msg: {$o->getMessage()}\n--file: {$o->getFile()} L#{$o->getLine()}\n--sql: {$o->getSql()}";
            $s .= "\n--trace: " . str_replace("\n", "\n        ", $o->getTraceAsString());
        }
        if($stmt !== null){
            list($sql, $time, $rows) = $stmt;
            $s .= "\n--sql: $sql\n--time: " . sprintf('%0.1f ms', $time * 1000) . "\n--rows: $rows";
        }

        $this->writeFile("$s\n\n");

    }


    /**
     * Append content to file.
     * @param string $content
     * @return void
     */
    private function writeFile($content){
        $handle = fopen($this->file, 'a');
        flock($handle, LOCK_EX);
        fwrite($handle, $content);
        fclose($handle);
    }


    /*    ============    Implementation of Nette\IDebugPanel    ============    */


    public function getId(){
        return __CLASS__;
    }


    public function getTab(){
        return '<span title="Neevo database layer (rev. #'.Neevo::REVISION.')">'
                    .'<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAFmSURBVBgZBcHPa85xAAfw1/Psy9bEFE+MgwyzrLS4KqUQRauVODohB/+As7OzGilZrpQki4OLgyiEImFWmqb5sfZ4vt/P2+vVitn+nMyZMpZdKeV1PpTpMjvZALQe7clMZ+9mawyKJb99sfA0p6e+AR4+/pySJEmSJOnlRe7cjIhoZ3wTAICtyjGAqojvBvRbJZYt+maHAqAqovLTiqj90lWJAqCK6DOgUumpBTPqDkBVRK2n1tJ477tRI+LKoe71pQdXz7eLaNRqjcaCA2LEqLHZY9uac8cHqyJ6ehp9Gpux5LEB+zSGbtxfbhdFrdaIuzYa9spFnYW3y1tMnL2QdmNRRz/4a1HXBvN60vttzry+qTdfJ9urh3WsM+GHrvWe5V/G1zXuTy8cbsWt7eVymWoPDaq9c9Anu634aMS0uaoVwLW19c66PL/05+zQif33fnh5unt7+dGToyIiIiIiTuVIIiL+A271xrBxnHZ+AAAAAElFTkSuQmCC">'
                    .($this->numQueries ? $this->numQueries : 'No') . ' queries'
                    .($this->totalTime ? ' / ' . sprintf('%0.1f', $this->totalTime * 1000) . ' ms' : '')
                    .'</span>';
    }


    public function getPanel(){
        if(!$this->numQueries) return;

        $s = '';
        foreach($this->tickets as $ticket){
            list(, $event, $stmt) = $ticket;
            if($stmt === null) continue;

            list($sql, $time, $rows) = $stmt;
            $time = sprintf('%0.1f ms', $time * 1000);

            $s .= "<tr><td>$time</td><td>{$this->formatSql($sql)}</td><td>$rows</td></tr>";
        }

        return "<style> #nette-debug-NeevoObserver td.sql { background: white !important } </style>"
                    ."<h1 style=\"padding-right:2em\">Queries: $this->numQueries"
                    .($this->totalTime ? ', time: ' . sprintf('%0.1f', $this->totalTime * 1000) . ' ms' : '')
                    .'</h1><table><tr><th>Time</th><th>SQL</th><th>Rows</th></tr>'
                    .$s.'</table>';
    }


    private function formatSql($sql, $len = 100){
        if(strlen($sql) > $len){
            $sql = substr($sql, 0, $len) . "\xE2\x80\xA6";
        }
        return Neevo::highlightSql($sql);
    }


}
