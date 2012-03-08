<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Nette;

use Neevo,
	Nette\Diagnostics\IBarPanel,
	Nette\Diagnostics\Debugger,
	Nette\Diagnostics\Helpers,
	Nette\Diagnostics\BlueScreen,
	Nette\Utils\Html;


/**
 * DebugBar panel informing about performed queries.
 */
class DebugBar implements Neevo\IObserver, IBarPanel {


	/** @var array */
	private $tickets = array();

	/** @var int */
	private $numQueries = 0;

	/** @var int */
	private $totalTime = 0;

	/** @var array */
	private $failedQuerySource;

	/** @var bool */
	private $explain = true;


	/**
	 * Do not call directly, use static method register().
	 * @param bool $explain
	 * @return void
	 */
	public function __construct($explain){
		$this->explain = (bool) $explain;
	}


	/**
	 * Register Neevo DebugBar and Bluescreen panels.
	 * @param Neevo\Manager $neevo
	 * @return void
	 */
	public static function register(Neevo\Manager $neevo){
		$panel = new static($neevo->getConnection()->getConfig('explain'));
		$neevo->attachObserver($panel, Debugger::$productionMode ? self::EXCEPTION : self::QUERY + self::EXCEPTION);

		// Register DebugBar panel
		if(!Debugger::$productionMode)
			Debugger::$bar->addPanel($panel);

		// Register Bluescreen panel
		Debugger::$blueScreen->addPanel(callback($panel, 'renderException'));
	}


	/**
	 * Receives update from observable subject.
	 * @param Neevo\IObservable $subject
	 * @param int $event
	 * @return void
	 */
	public function updateStatus(Neevo\IObservable $subject, $event){
		$source = null;
		$path = realpath(defined('NEEVO_DIR') ? NEEVO_DIR : __DIR__ . '/../../');
		foreach(debug_backtrace(false) as $t){
			if(isset($t['file']) && strpos($t['file'], $path) !== 0){
				$source = array($t['file'], (int) $t['line']);
				break;
			}
		}

		if($event & self::QUERY){
			$this->numQueries++;
			$this->totalTime += $subject->getTime();

			if($subject instanceof Neevo\Result){
				try{
					$rows = count($subject);
				} catch(\Exception $e){
					$rows = '?';
				}
				$explain = $this->explain ? $subject->explain() : null;
			} else{
				$rows = '-';
			}

			$this->tickets[] = array(
				'sql' => (string) $subject,
				'time' => $subject->getTime(),
				'rows' => $rows,
				'source' => $source,
				'connection' => $subject->getConnection(),
				'explain' => isset($explain) ? $explain : null
			);
		} elseif($event === self::EXCEPTION){
			$this->failedQuerySource = $source;
		}
	}


	/**
	 * Renders SQL query string to Nette debug bluescreen when available.
	 * @param Neevo\NeevoException $e
	 * @return array
	 */
	public function renderException($e){
		if($e instanceof Neevo\NeevoException && $e->getSql()){
			list($file, $line) = $this->failedQuerySource;
			return array(
				'tab' => 'SQL',
				'panel' => Neevo\Manager::highlightSql($e->getSql())
				. '<p><b>File:</b> ' . Helpers::editorLink($file, $line)
				. ' &nbsp; <b>Line:</b> ' . ($line ? : 'n/a') . '</p>'
				. (is_file($file) ? BlueScreen::highlightFile($file, $line) : '')
				. 'Neevo ' . Neevo\Manager::VERSION . ', revision ' . Neevo\Manager::REVISION
			);
		}
	}


	/**
	 * Renders Nette DebugBar tab.
	 * @return string
	 */
	public function getTab(){
		$queries = $this->numQueries;
		$time = $this->totalTime;
		ob_start();
		include_once __DIR__ . '/templates/DebugBar.tab.phtml';
		return ob_get_clean();
	}


	/**
	 * Renders Nette DebugBar panel.
	 * @return string
	 */
	public function getPanel(){
		if(!$this->numQueries){
			return '';
		}

		$timeFormat = function($time){
				return sprintf('%0.3f', $time * 1000);
			};

		$tickets = $this->tickets;
		$totalTime = $this->totalTime;
		$numQueries = $this->numQueries;

		ob_start();
		include_once __DIR__ . '/templates/DebugBar.panel.phtml';
		return ob_get_clean();
	}


}