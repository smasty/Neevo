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


	public function parseWhere(){
		$date = new DateTime;
		return array(
			array(array('bar', null), " WHERE (:bar IS NULL)"),
			array(array('bar', true), " WHERE (:bar)"),
			array(array('bar', false), " WHERE (NOT :bar)"),
			array(array('bar', array(1, 2)), " WHERE (:bar IN (1, 2))"),
			array(array('bar', new NeevoLiteral('NOW()')), " WHERE (:bar = NOW())"),
			array(array('bar', $date), " WHERE (:bar = '{$date->format('Y-m-d H:i:s')}')"),
			array(array('bar', 'baz'), " WHERE (:bar = 'baz')"),
			array(array(':bar = %i AND :baz = %s', 1, 2), " WHERE (:bar = 1 AND :baz = '2')")
		);
	}


	/**
	 * @dataProvider parseWhere
	 */
	public function testParseWhere($inputParams, $output){
		A::assertEquals($output, $this->parser(call_user_func_array(array($this->createSelect(), 'where'), $inputParams))->parseWhere());
	}


	public function testParseWhereSimpleGlue(){
		A::assertEquals(
			" WHERE (:bar) AND (:baz)", $this->parser($this->createSelect()->where('bar')->and('baz'))->parseWhere()
		);
	}


	public function testParseWhereModifiersGlue(){
		A::assertEquals(
			" WHERE (:bar = 1, :baz = 2) AND (:bar)", $this->parser($this->createSelect()->where(':bar = %i, :baz = %i', 1, 2)->and(':bar'))
				->parseWhere()
		);
	}


	public function testParseSorting(){
		A::assertEquals(
			" ORDER BY :bar, :baz DESC", $this->parser($this->createSelect()->order(':bar')->order(':baz', Neevo::DESC))
				->parseSorting()
		);
	}


	public function testParseGrouping(){
		A::assertEquals(
			" GROUP BY :bar HAVING :baz", $this->parser($this->createSelect()->group(':bar', ':baz'))->parseGrouping()
		);
	}


	public function testParseSource(){
		A::assertEquals(":foo", $this->parser($this->createInsert('foo', (array) 1))->parseSource());
		A::assertEquals(":foo", $this->parser($this->createSelect())->parseSource());
	}


	public function testParseSourceJoin(){
		A::assertEquals(
			":foo LEFT JOIN :bar ON :bar.id = :foo.bar_id", $this->parser($this->createSelect()->leftJoin(':bar', ':bar.id = :foo.bar_id'))
				->parseSource()
		);
	}


	public function testApplyLimit(){
		A::assertEquals(
			"foo LIMIT 15 OFFSET 5", $this->parser($this->createSelect()->limit(15, 5))->applyLimit('foo')
		);
	}


	public function parseFieldName(){
		return array(
			array(':foo', ':foo'),
			array(new NeevoLiteral('NOW()'), 'NOW()'),
			array('*', '*'),
			array('foo bar', 'foo bar'),
			array(':foo.bar', ':foo.bar'),
		);
	}


	/**
	 * @dataProvider parseFieldName
	 */
	public function testParseFieldName($input, $output){
		A::assertEquals($output, $this->parser($this->createSelect())->parseFieldName($input));
	}


	public function escapeValueNoType(){
		$date = new DateTime;
		return array(
			array(null, 'NULL'),
			array(array('1', 'foo'), array(1, "'foo'")),
			array('1', 1),
			array('foo', "'foo'"),
			array(new NeevoLiteral(1), 1),
			array($date, $date->format("'Y-m-d H:i:s'"))
		);
	}


	/**
	 * @dataProvider escapeValueNoType
	 */
	public function testEscapeValue($input, $output){
		A::assertEquals($output, $this->parser($this->createSelect())
				->escapeValue($input));
	}


	public function escapeValueType(){
		return array(
			array(array('1', 'foo'), array(Neevo::FLOAT, Neevo::TEXT), array(1.0, "'foo'")),
			array('1', Neevo::INT, 1),
			array('1', Neevo::FLOAT, 1.0),
			array(array(1, 'foo'), Neevo::ARR, "(1, 'foo')"),
			array('1', Neevo::LITERAL, '1'),
			array('foo', Neevo::BINARY, "bin:'foo'")
		);
	}


	/**
	 * @dataProvider escapeValueType
	 */
	public function testEscapeValueType($input, $type, $output){
		A::assertEquals($output, $this->parser($this->createSelect())
				->escapeValue($input, $type));
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
			'SELECT * FROM :foo WHERE (:id = 5) GROUP BY :id ORDER BY :id', $this->parser($this->createSelect()->where(':id', 5)
					->group(':id')->order(':id'))->parse()
		);
	}


	public function testParseInsert(){
		A::assertEquals(
			"INSERT INTO :table (:id, :name) VALUES (5, 'John Doe')", $this->parser($this->createInsert(':table', array(
					'id' => 5,
					'name' => 'John Doe'
				)))->parse()
		);
	}


	public function testParseUpdate(){
		A::assertEquals(
			"UPDATE :table SET :id = 5, :name = 'John Doe' WHERE (:id = 5)", $this->parser($this->createUpdate(':table', array(
					'id' => 5,
					'name' => 'John Doe'
				))->where(':id', 5))->parse()
		);
	}


	public function testParseDelete(){
		A::assertEquals(
			'DELETE FROM :table WHERE (:id = 5)', $this->parser($this->createDelete(':table')->where(':id', 5))->parse()
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
			':tab1 LEFT JOIN (SELECT * FROM :tab2) :tab2 ON :tab1.id = :tab2.tab1_id', $this->parser($result->leftJoin($subquery->as('tab2'), ':tab1.id = :tab2.tab1_id'))->parseSource()
		);
	}


	public function testParseWhereSimpleSubquery(){
		A::assertEquals(
			" WHERE (:bar IN (SELECT * FROM :foo))", $this->parser($this->createSelect()->where('bar', $this->createSelect()))->parseWhere()
		);
	}


}
