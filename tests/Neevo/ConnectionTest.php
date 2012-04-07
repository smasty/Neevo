 <?php


/**
 * Tests for Neevo\Connection.
 */
class ConnectionTest extends PHPUnit_Framework_TestCase {


	/** @var Neevo\Connection */
	protected $instance;


	protected function setUp(){
		$config = array(
			'testConfig' => true,
			'driver' => 'Dummy'
		);
		$this->instance = new Neevo\Connection($config);
	}


	protected function tearDown(){
		unset($this->instance);
	}


	public function testConfigFormatString(){
		$connection = new Neevo\Connection('driver=Dummy');

		$this->assertInstanceOf('Neevo\\Drivers\\DummyDriver', $connection->getDriver());
	}


	public function testConfigFormatTraversable(){
		$config = new ArrayObject(array('driver' => 'Dummy'));
		$connection = new Neevo\Connection($config);

		$this->assertInstanceOf('Neevo\\Drivers\\DummyDriver', $connection->getDriver());
	}


	public function testConfigFormatElse(){
		try{
			$msg = new Neevo\Connection(false);
		} catch(InvalidArgumentException $e){
			$msg = $e->getMessage();
		}

		$this->assertStringStartsWith('Configuration must be', $msg);
	}


	public function testAutoSetDriver(){
		$this->assertInstanceOf('Neevo\\Drivers\\DummyDriver', $this->instance->getDriver());
	}


	public function testSetDriverNoFile(){
		try{
			$msg = new Neevo\Connection(array(
					'driver' => 'Foo'
				));
		} catch(Neevo\DriverException $e){
			$msg = $e->getMessage();
		}

		$this->assertStringEndsWith('does not exist.', $msg);
	}


	public function testSetDriverNoDriver(){
		try{
			$msg = new Neevo\Connection(array(
					'driver' => 'Wrong'
				));
		} catch(Neevo\DriverException $e){
			$msg = $e->getMessage();
		}

		$this->assertStringStartsWith("Wrong driver file", $msg);
	}


	public function testAutoSetParser(){
		$this->assertEquals($this->instance->getParser(), 'Neevo\\Parser');
	}


	public function testSetCustomParser(){
		$connection = new Neevo\Connection(array(
				'driver' => 'DummyParser'
			));

		$this->assertEquals($connection->getParser(), 'Neevo\\Drivers\\DummyParserDriver');
	}


	public function testSetCache(){
		$cache = new Neevo\Cache\SessionStorage;
		$this->instance->setCache($cache);

		$this->assertEquals(spl_object_hash($this->instance->getCache()), spl_object_hash($cache));
	}


	public function testAutoSetCache(){
		$this->assertInstanceOf('Neevo\\Cache\\MemoryStorage', $this->instance->getCache());
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
		Neevo\Connection::alias($config, 'key', 'alias');

		$this->assertEquals('value', $config['key']);
	}


	public function testGetPrefix(){
		$this->assertEquals('', $this->instance->getPrefix());
	}


	public function testArrayAccess(){
		$this->assertEquals('Dummy', $this->instance['driver'], 'offsetGet()');
		$this->assertTrue(isset($this->instance['driver']), 'offsetExists()');

		$this->instance['driver'] = 'foo';
		unset($this->instance['driver']);
		$this->assertEquals('Dummy', $this->instance['driver'], 'offsetSet(), offsetUnset()');

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
