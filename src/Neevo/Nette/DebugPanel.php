<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2011 Martin Srank (http://smasty.net)
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
 * Provides Nette DebugBar panel with info about performed queries.
 */
class DebugPanel implements Neevo\Observer\Observer, IBarPanel {


	public static $templateFile = '/DebugPanel.phtml';

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
	 * @param bool $explain Run EXPLAIN on all SELECT queries?
	 * @return void
	 */
	public static function register(Neevo\Manager $neevo, $explain){
		$panel = new static($explain);
		$neevo->attachObserver($panel, Debugger::$productionMode
			? self::EXCEPTION : self::QUERY + self::EXCEPTION);

		// Register DebugBar panel
		if(!Debugger::$productionMode)
			Debugger::$bar->addPanel($panel);

		// Register Bluescreen panel
		Debugger::$blueScreen->addPanel(callback($panel, 'renderException'), __CLASS__);
	}


	/**
	 * Receives update from observable subject.
	 * @param Neevo\Observer\Subject $subject
	 * @param int $event
	 * @return void
	 */
	public function updateStatus(Neevo\Observer\Subject $subject, $event){
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
				'sql' => $subject->__toString(),
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
				. '<p><b>File:</b> '
				. Helpers::editorLink($file, $line)
				. " &nbsp; <b>Line:</b> $line</p>"
				. (is_file($file)
					? '<pre>' . BlueScreen::highlightFile($file, $line) . '</pre>'
					: '')
			);
		}
	}


	/**
	 * Renders Nette DebugBar tab.
	 * @return string
	 */
	public function getTab(){
		return '<span title="Neevo v' . Neevo\Manager::VERSION . ', revision ' . Neevo\Manager::REVISION . '">'
		. '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAEYSURBVBgZBcHPio5hGAfg6/2+R980k6wmJgsJ5U/ZOAqbSc2GnXOwUg7BESgLUeIQ1GSjLFnMwsKGGg1qxJRmPM97/1zXFAAAAEADdlfZzr26miup2svnelq7d2aYgt3rebl585wN6+K3I1/9fJe7O/uIePP2SypJkiRJ0vMhr55FLCA3zgIAOK9uQ4MS361ZOSX+OrTvkgINSjS/HIvhjxNNFGgQsbSmabohKDNoUGLohsls6BaiQIMSs2FYmnXdUsygQYmumy3Nhi6igwalDEOJEjPKP7CA2aFNK8Bkyy3fdNCg7r9/fW3jgpVJbDmy5+PB2IYp4MXFelQ7izPrhkPHB+P5/PjhD5gCgCenx+VR/dODEwD+A3T7nqbxwf1HAAAAAElFTkSuQmCC" width="16" height="16">'
		. ($this->numQueries ? $this->numQueries : 'No') . ' queries'
		. ($this->totalTime ? ' / ' . sprintf('%0.1f', $this->totalTime * 1000) . ' ms' : '')
		. '</span>';
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
		$sourceLink = function($source){
			$el = Html::el('a');
			$el->class = 'link';
			$el->href(strtr(Debugger::$editor,
				array('%file' => rawurlencode($source[0]), '%line' => $source[1])
			));
			$el->setText(basename(dirname($source[0])) . '/' . basename($source[0]) . ":$source[1]");
			$el->title = implode(':', $source);

			return $el;
		};

		$tickets = $this->tickets;
		$totalTime = $this->totalTime;
		$numQueries = $this->numQueries;

		ob_start();
		include_once __DIR__ . self::$templateFile;
		return ob_get_clean();
	}


}