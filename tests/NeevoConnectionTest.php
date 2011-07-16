 <?php


/**
 * Tests for NeevoConnection.
 */
class NeevoConnectionTest extends PHPUnit_Framework_TestCase {


	/** @var NeevoConnection */
	protected $instance;


	protected function setUp(){
		$config = array(
			'testConfig' => true,
			'driver' => 'Dummy'
		);
		$this->instance = new NeevoConnection($config);
	}


	protected function tearDown(){
		unset($this->instance);
	}


	public function testConfigFormatString(){
		$connection = new NeevoConnection('driver=Dummy');

		$this->assertInstanceOf('NeevoDriverDummy', $connection->getDriver());
	}


	public function testConfigFormatTraversable(){
		$config = new ArrayObject(array('driver' => 'Dummy'));
		$connection = new NeevoConnection($config);

		$this->assertInstanceOf('NeevoDriverDummy', $connection->getDriver());
	}


	public function testConfigFormatElse(){
		try{
			$msg = new NeevoConnection(false);
		} catch(InvalidArgumentException $e){
			$msg = $e->getMessage();
		}

		$this->assertStringStartsWith('Configuration must be', $msg);
	}


	public function testAutoSetDriver(){
		$this->assertInstanceOf('NeevoDriverDummy', $this->instance->getDriver());
	}


	public function testSetDriverNoFile(){
		try{
			$msg = new NeevoConnection(array(
					'driver' => 'Foo'
				));
		} catch(NeevoDriverException $e){
			$msg = $e->getMessage();
		}

		$this->assertStringEndsWith('does not exist.', $msg);
	}


	public function testSetDriverNoDriver(){
		try{
			$msg = new NeevoConnection(array(
					'driver' => 'Wrong'
				));
		} catch(NeevoDriverException $e){
			$msg = $e->getMessage();
		}

		$this->assertStringStartsWith("Class 'NeevoDriverWrong'", $msg);
	}


	public function testAutoSetParser(){
		$this->assertEquals($this->instance->getParser(), 'NeevoParser');
	}


	public function testSetCustomParser(){
		$connection = new NeevoConnection(array(
				'driver' => 'Parser'
			));

		$this->assertEquals($connection->getParser(), 'NeevoDriverParser');
	}


	public function testSetCache(){
		$cache = new NeevoCacheSession;
		$this->instance->setCache($cache);

		$this->assertEquals(spl_object_hash($this->instance->getCache()), spl_object_hash($cache));
	}


	public function testAutoSetCache(){
		$this->assertInstanceOf('NeevoCacheMemory', $this->instance->getCache());
	}


	public function testSetConfig(){
		$this->assertTrue($this->instance->getConfig('testConfig'));
		$this->assertInternalType('array', $this->instance->getConfig());
	}


	public function testAttachObserver(){
		$observer = new DummyObserver;
		$this->instance->attachObserver($observer, 1);
		$this->instance->notifyObservers(1);

		$this->assertTrue($observer->isNotified());
	}


	public function testDetachObserver(){
		$observer = new DummyObserver;
		$this->instance->attachObserver($observer, 1);
		$this->instance->detachObserver($observer);
		$this->instance->notifyObservers(1);

		$this->assertFalse($observer->isNotified());
	}


	public function testAlias(){
		$config = array(
			'alias' => 'value',
		);
		NeevoConnection::alias($config, 'key', 'alias');

		$this->assertEquals('value', $config['key']);
	}


	public function testGetPrefix(){
		$this->assertEquals('', $this->instance->getPrefix());
	}


	public function testArrayAccess(){
		$this->assertEquals('Dummy', $this->instance['driver'], 'offsetGet()');
		$this->assertTrue(isset($this->instance['driver']), 'offsetSet()');
	}


	public function testCloseConnection(){
		$this->instance->attachObserver($o = new DummyObserver, DummyObserver::DISCONNECT);
		$this->instance->__destruct();

		$this->assertTrue($this->instance->getDriver()->isClosed());
		$this->assertTrue($o->isNotified());
	}


}



/**
 * Class with Neevo driver convention name, but not a driver.
 */
class NeevoDriverWrong {}

?>
