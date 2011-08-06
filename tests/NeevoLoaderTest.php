<?php


/**
 * Tests for NeevoLoader.
 */
class NeevoLoaderTest extends PHPUnit_Framework_TestCase {


	protected function setUp(){
		NeevoLoader::getInstance()->unregister();
	}


	protected function tearDown(){
		NeevoLoader::getInstance()->register();
	}


	public function testRegister(){
		$cb = array(NeevoLoader::getInstance(), 'tryLoad');
		$this->assertFalse(in_array($cb, spl_autoload_functions()));
		NeevoLoader::getInstance()->register();
		$this->assertTrue(in_array($cb, spl_autoload_functions()));
	}


	public function testUnregister(){
		$cb = array(NeevoLoader::getInstance(), 'tryLoad');
		$this->assertFalse(in_array($cb, spl_autoload_functions()));
		NeevoLoader::getInstance()->register();
		$this->assertTrue(in_array($cb, spl_autoload_functions()));
		NeevoLoader::getInstance()->unregister();
		$this->assertFalse(in_array($cb, spl_autoload_functions()));
	}


	public function testTryLoad(){
		$this->assertTrue(NeevoLoader::getInstance()->tryLoad('Neevo'));
	}


	public function testTryLoadFailed(){
		$this->assertFalse(class_exists($class = '__DummyClass', false));
		$this->assertFalse(NeevoLoader::getInstance()->tryLoad($class));
	}


}
