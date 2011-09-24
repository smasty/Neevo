<?php


/**
 * Tests for Neevo\Loader.
 */
class LoaderTest extends PHPUnit_Framework_TestCase {


	protected function setUp(){
		Neevo\Loader::getInstance()->unregister();
	}


	protected function tearDown(){
		Neevo\Loader::getInstance()->register();
	}


	public function testRegister(){
		$cb = array(Neevo\Loader::getInstance(), 'tryLoad');
		$this->assertFalse(in_array($cb, spl_autoload_functions()));
		Neevo\Loader::getInstance()->register();
		$this->assertTrue(in_array($cb, spl_autoload_functions()));
	}


	public function testUnregister(){
		$cb = array(Neevo\Loader::getInstance(), 'tryLoad');
		$this->assertFalse(in_array($cb, spl_autoload_functions()));
		Neevo\Loader::getInstance()->register();
		$this->assertTrue(in_array($cb, spl_autoload_functions()));
		Neevo\Loader::getInstance()->unregister();
		$this->assertFalse(in_array($cb, spl_autoload_functions()));
	}


	public function testTryLoad(){
		$this->assertTrue(Neevo\Loader::getInstance()->tryLoad('Neevo\\Manager'));
	}


	public function testTryLoadFailed(){
		$this->assertFalse(class_exists($class = '__DummyClass', false));
		$this->assertFalse(Neevo\Loader::getInstance()->tryLoad($class));
	}


}
