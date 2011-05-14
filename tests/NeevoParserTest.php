<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoParser.
 */
class NeevoParserTest extends PHPUnit_Framework_TestCase {


	/** @var NeevoConnection */
	private $connection;


	protected function setUp(){
		$this->connection = new NeevoConnection('driver=Parser');
	}


	protected function tearDown(){
		unset($this->connection);
	}


	/** @return NeevoDriverParser */
	private function parser(NeevoStmtBase $stmt){
		$instance = $this->connection->getParser();
		return new $instance($stmt);
	}


	private function createSelect(){
		return new NeevoResult($this->connection, 'foo');
	}


	private function createInsert($table, array $values){
		return NeevoStmt::createInsert($this->connection, $table, $values);
	}


	private function createUpdate($table, array $data){
		return NeevoStmt::createUpdate($this->connection, $table, $data);
	}


	private function createDelete($table){
		return NeevoStmt::createDelete($this->connection, $table);
	}


	public function testParseWhereSimpleNull(){
		A::assertEquals(
			" WHERE (:bar IS NULL)",
			$this->parser($this->createSelect()->where('bar', null))->parseWhere()
		);
	}


	public function testParseWhereSimpleTrue(){
		A::assertEquals(
			" WHERE (:bar)",
			$this->parser($this->createSelect()->where('bar', true))->parseWhere()
		);
	}


	public function testParseWhereSimpleFalse(){
		A::assertEquals(
			" WHERE (NOT :bar)",
			$this->parser($this->createSelect()->where('bar', false))->parseWhere()
		);
	}


	public function testParseWhereSimpleArray(){
		A::assertEquals(
			" WHERE (:bar IN (1, 2))",
			$this->parser($this->createSelect()->where('bar', array(1, 2)))->parseWhere()
		);
	}


	public function testParseWhereSimpleLiteral(){
		A::assertEquals(
			" WHERE (:bar = NOW())",
			$this->parser($this->createSelect()->where('bar', new NeevoLiteral('NOW()')))->parseWhere()
		);
	}


	public function testParseWhereSimpleDateTime(){
		$t = new DateTime;
		A::assertEquals(
			" WHERE (:bar = '{$t->format('Y-m-d H:i:s')}')",
			$this->parser($this->createSelect()->where('bar', $t))->parseWhere()
		);
	}


	public function testParseWhereSimpleValue(){
		A::assertEquals(
			" WHERE (:bar = 'baz')",
			$this->parser($this->createSelect()->where('bar', 'baz'))->parseWhere()
		);
	}


	public function testParseWhereSimpleGlue(){
		A::assertEquals(
			" WHERE (:bar) AND (:baz)",
			$this->parser($this->createSelect()->where('bar')->and('baz'))->parseWhere()
		);
	}


	public function testParseWhereModifiers(){
		A::assertEquals(
			" WHERE (:bar = 1, :baz = 2)",
			$this->parser($this->createSelect()->where(':bar = %i, :baz = %i', 1, 2))->parseWhere()
		);
	}


	public function testParseWhereModifiersGlue(){
		A::assertEquals(
			" WHERE (:bar = 1, :baz = 2) AND (:bar)",
			$this->parser($this->createSelect()->where(':bar = %i, :baz = %i', 1, 2)->and(':bar'))
			->parseWhere()
		);
	}


	public function testParseSorting(){
		A::assertEquals(
			" ORDER BY :bar, :baz DESC",
			$this->parser($this->createSelect()->order(':bar')->order(':baz', Neevo::DESC))
			->parseSorting()
		);
	}


	public function testParseGrouping(){
		A::assertEquals(
			" GROUP BY :bar HAVING :baz",
			$this->parser($this->createSelect()->group(':bar', ':baz'))->parseGrouping()
		);
	}


	public function testParseSource(){
		A::assertEquals(":foo",$this->parser($this->createInsert('foo', (array) 1))->parseSource());
		A::assertEquals(":foo",$this->parser($this->createSelect())->parseSource());
	}


	public function testParseSourceJoin(){
		A::assertEquals(
			":foo LEFT JOIN :bar ON :bar.id = :foo.bar_id",
			$this->parser($this->createSelect()->leftJoin(':bar', ':bar.id = :foo.bar_id'))
			->parseSource()
		);
	}


	public function testApplyLimit(){
		A::assertEquals(
			"foo LIMIT 15 OFFSET 5",
			$this->parser($this->createSelect()->limit(15, 5))->applyLimit('foo')
		);
	}


	public function testparseFieldName(){
		A::assertEquals(":foo", $this->parser($this->createSelect())->parseFieldName(':foo'));
	}


	public function testparseFieldNameLiteral(){
		A::assertEquals("NOW()", $this->parser($this->createSelect())
			->parseFieldName(new NeevoLiteral('NOW()')));
	}


	public function testparseFieldNameAsterisk(){
		A::assertEquals("*", $this->parser($this->createSelect())->parseFieldName('*'));
	}


	public function testparseFieldNameSpace(){
		A::assertEquals("foo bar", $this->parser($this->createSelect())->parseFieldName('foo bar'));
	}


	public function testparseFieldNameDotted(){
		A::assertEquals(":foo.bar", $this->parser($this->createSelect())->parseFieldName(':foo.bar'));
	}


	public function testEscapeValueNullNoType(){
		A::assertEquals('NULL', $this->parser($this->createSelect())->escapeValue(null));
	}


	public function testEscapeValueArrayNoType(){
		A::assertEquals(array(1, "'foo'"), $this->parser($this->createSelect())
			->escapeValue(array('1', 'foo')));
	}


	public function testEscapeValueNoType(){
		A::assertEquals(1, $this->parser($this->createSelect())->escapeValue('1'));
		A::assertEquals("'foo'", $this->parser($this->createSelect())->escapeValue('foo'));
		A::assertEquals(1, $this->parser($this->createSelect())->escapeValue(new NeevoLiteral(1)));

		$d = new DateTime;
		A::assertEquals($d->format("'Y-m-d H:i:s'"), $this->parser($this->createSelect())
			->escapeValue($d));
	}


	public function testEscapeValueMoreTypes(){
		A::assertEquals(array(1.0, "'foo'"), $this->parser($this->createSelect())
			->escapeValue(array('1', 'foo'), array(Neevo::FLOAT, Neevo::TEXT)));
	}


	public function testEscapeValueInt(){
		A::assertEquals(1, $this->parser($this->createSelect())->escapeValue('1', Neevo::INT));
	}


	public function testEscapeValueFloat(){
		A::assertEquals(1.0, $this->parser($this->createSelect())->escapeValue('1', Neevo::FLOAT));
	}


	public function testEscapeValueArray(){
		A::assertEquals("(1, 'foo')", $this->parser($this->createSelect())
			->escapeValue(array(1, 'foo'), Neevo::ARR));
	}


	public function testEscapeValueLiteral(){
		A::assertEquals('1', $this->parser($this->createSelect())->escapeValue('1', Neevo::LITERAL));
	}


	public function testEscapeValueBinary(){
		A::assertEquals("bin:'foo'", $this->parser($this->createSelect())
			->escapeValue('foo', Neevo::BINARY));
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testEscapeValueUnknownType(){
		$this->parser($this->createSelect())->escapeValue('foo', 'unknown_type');
	}


	public function testTryDelimiteLiteral(){
		A::assertEquals('NOW()', $this->parser($this->createSelect())
			->tryDelimite(new NeevoLiteral('NOW()')));
	}


	public function testParseSelect(){
		A::assertEquals(
			'SELECT * FROM :foo WHERE (:id = 5) GROUP BY :id ORDER BY :id',
			$this->parser($this->createSelect()->where(':id', 5)
				->group(':id')->order(':id'))->parse()
		);
	}


	public function testParseInsert(){
		A::assertEquals(
			"INSERT INTO :table (:id, :name) VALUES (5, 'John Doe')",
			$this->parser($this->createInsert(':table', array(
				'id' => 5,
				'name' => 'John Doe'
			)))->parse()
		);
	}


	public function testParseUpdate(){
		A::assertEquals(
			"UPDATE :table SET :id = 5, :name = 'John Doe' WHERE (:id = 5)",
			$this->parser($this->createUpdate(':table', array(
				'id' => 5,
				'name' => 'John Doe'
			))->where(':id', 5))->parse()
		);
	}


	public function testParseDelete(){
		A::assertEquals(
			'DELETE FROM :table WHERE (:id = 5)',
			$this->parser($this->createDelete(':table')->where(':id', 5))->parse()
		);
	}


	public function testParseSourceSubquery(){
		$subquery = new NeevoResult($this->connection, 'foo');
		$result = new NeevoResult($this->connection, $subquery->as('alias'));
		A::assertEquals('(SELECT * FROM :foo) :alias', $this->parser($result)->parseSource());
	}


	public function testParseSourceJoinSubquery(){
		$subquery = new NeevoResult($this->connection, 'tab2');
		$result = new NeevoResult($this->connection, 'tab1');
		A::assertEquals(
			':tab1 LEFT JOIN (SELECT * FROM :tab2) :tab2 ON :tab1.id = :tab2.tab1_id',
			$this->parser($result->leftJoin($subquery->as('tab2'), ':tab1.id = :tab2.tab1_id'))->parseSource()
		);
	}


	public function testParseWhereSimpleSubquery(){
		A::assertEquals(
			" WHERE (:bar IN (SELECT * FROM :foo))",
			$this->parser($this->createSelect()->where('bar', $this->createSelect()))->parseWhere()
		);
	}

}
