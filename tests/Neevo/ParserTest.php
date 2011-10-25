<?php


/**
 * Tests for Neevo\Parser.
 */
class ParserTest extends PHPUnit_Framework_TestCase {


	/** @var Neevo\Connection */
	private $connection;


	protected function setUp(){
		$this->connection = new Neevo\Connection('driver=DummyParser');
	}


	protected function tearDown(){
		unset($this->connection);
	}


	/** @return NeevoDriverParser */
	private function parser(Neevo\BaseStatement $stmt){
		$instance = $this->connection->getParser();
		return new $instance($stmt);
	}


	private function createSelect(){
		return new Neevo\Result($this->connection, 'foo');
	}


	private function createInsert($table, array $values){
		return Neevo\Statement::createInsert($this->connection, $table, $values);
	}


	private function createUpdate($table, array $data){
		return Neevo\Statement::createUpdate($this->connection, $table, $data);
	}


	private function createDelete($table){
		return Neevo\Statement::createDelete($this->connection, $table);
	}


	public function parseWhere(){
		$date = new DateTime;
		return array(
			array(array('bar', null), " WHERE (:bar IS NULL)"),
			array(array('bar', true), " WHERE (:bar)"),
			array(array('bar', false), " WHERE (NOT :bar)"),
			array(array('bar', array(1, 2)), " WHERE (:bar IN (1, 2))"),
			array(array('bar', new Neevo\Literal('NOW()')), " WHERE (:bar = NOW())"),
			array(array('bar', $date), " WHERE (:bar = '{$date->format('Y-m-d H:i:s')}')"),
			array(array('bar', 'baz'), " WHERE (:bar = 'baz')"),
			array(array(':bar = %i AND :baz = %s', 1, 2), " WHERE (:bar = 1 AND :baz = '2')")
		);
	}


	/**
	 * @dataProvider parseWhere
	 */
	public function testParseWhere($inputParams, $output){
		$this->assertEquals($output, $this->parser(call_user_func_array(array($this->createSelect(), 'where'), $inputParams))->parseWhere());
	}


	public function testParseWhereSimpleGlue(){
		$this->assertEquals(
			" WHERE (:bar) AND (:baz)", $this->parser($this->createSelect()->where('bar')->and('baz'))->parseWhere()
		);
	}


	public function testParseWhereModifiersGlue(){
		$this->assertEquals(
			" WHERE (:bar = 1, :baz = 2) AND (:bar)",
			$this->parser($this->createSelect()->where(':bar = %i, :baz = %i', 1, 2)->and(':bar'))
				->parseWhere()
		);
	}


	public function testParseSorting(){
		$this->assertEquals(
			" ORDER BY :bar, :baz DESC", $this->parser($this->createSelect()->order(':bar')->order(':baz', Neevo\Manager::DESC))
				->parseSorting()
		);
	}


	public function testParseGrouping(){
		$this->assertEquals(
			" GROUP BY :bar HAVING :baz", $this->parser($this->createSelect()->group(':bar', ':baz'))->parseGrouping()
		);
	}


	public function testParseSource(){
		$this->assertEquals(":foo", $this->parser($this->createInsert('foo', (array) 1))->parseSource());
		$this->assertEquals(":foo", $this->parser($this->createSelect())->parseSource());
	}


	public function testParseSourceJoin(){
		$this->assertEquals(
			":foo LEFT JOIN :bar ON :bar.id = :foo.bar_id",
			$this->parser($this->createSelect()->leftJoin(':bar', ':bar.id = :foo.bar_id'))
				->parseSource()
		);
	}


	public function testParseSourceJoinLiteral(){
		$this->assertEquals(
			":foo LEFT JOIN GETTABLE(bar) ON bar.id = foo.bar_id",
			$this->parser($this->createSelect()
				->leftJoin(new Neevo\Literal('GETTABLE(bar)'), new Neevo\Literal('bar.id = foo.bar_id')))
			->parseSource()
		);
	}


	public function testApplyLimit(){
		$this->assertEquals(
			"foo LIMIT 15 OFFSET 5",
			$this->parser($this->createSelect()->limit(15, 5))->applyLimit('foo')
		);
	}


	public function parseFieldName(){
		return array(
			array(':foo', ':foo'),
			array(new Neevo\Literal('NOW()'), 'NOW()'),
			array('*', '*'),
			array('foo bar', 'foo bar'),
			array(':foo.bar', ':foo.bar'),
		);
	}


	/**
	 * @dataProvider parseFieldName
	 */
	public function testParseFieldName($input, $output){
		$this->assertEquals($output, $this->parser($this->createSelect())->parseFieldName($input));
	}


	public function escapeValueNoType(){
		$date = new DateTime;
		return array(
			array(null, 'NULL'),
			array(array('1', 'foo'), array(1, "'foo'")),
			array('1', 1),
			array('foo', "'foo'"),
			array(new Neevo\Literal(1), 1),
			array($date, $date->format("'Y-m-d H:i:s'"))
		);
	}


	/**
	 * @dataProvider escapeValueNoType
	 */
	public function testEscapeValue($input, $output){
		$this->assertEquals($output, $this->parser($this->createSelect())
				->escapeValue($input));
	}


	public function escapeValueType(){
		return array(
			array(array('1', 'foo'), array(Neevo\Manager::FLOAT, Neevo\Manager::TEXT), array(1.0, "'foo'")),
			array('1', Neevo\Manager::INT, 1),
			array('1', Neevo\Manager::FLOAT, 1.0),
			array(array(1, 'foo'), Neevo\Manager::ARR, "(1, 'foo')"),
			array('1', Neevo\Manager::LITERAL, '1'),
			array('foo', Neevo\Manager::BINARY, "bin:'foo'")
		);
	}


	/**
	 * @dataProvider escapeValueType
	 */
	public function testEscapeValueType($input, $type, $output){
		$this->assertEquals($output, $this->parser($this->createSelect())
				->escapeValue($input, $type));
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testEscapeValueUnknownType(){
		$this->parser($this->createSelect())->escapeValue('foo', 'unknown_type');
	}


	public function testTryDelimiteLiteral(){
		$this->assertEquals('NOW()', $this->parser($this->createSelect())
				->tryDelimite(new Neevo\Literal('NOW()')));
	}


	public function testParseSelect(){
		$this->assertEquals(
			'SELECT * FROM :foo WHERE (:id = 5) GROUP BY :id ORDER BY :id',
			$this->parser($this->createSelect()->where(':id', 5)
					->group(':id')->order(':id'))->parse()
		);
	}


	public function testParseInsert(){
		$this->assertEquals(
			"INSERT INTO :table (:id, :name) VALUES (5, 'John Doe')",
			$this->parser($this->createInsert(':table', array(
					'id' => 5,
					'name' => 'John Doe'
				)))->parse()
		);
	}


	public function testParseUpdate(){
		$this->assertEquals(
			"UPDATE :table SET :id = 5, :name = 'John Doe' WHERE (:id = 5)",
			$this->parser($this->createUpdate(':table', array(
					'id' => 5,
					'name' => 'John Doe'
				))->where(':id', 5))->parse()
		);
	}


	public function testParseDelete(){
		$this->assertEquals(
			'DELETE FROM :table WHERE (:id = 5)',
			$this->parser($this->createDelete(':table')->where(':id', 5))->parse()
		);
	}


	public function testParseSourceSubquery(){
		$subquery = new Neevo\Result($this->connection, 'foo');
		$result = new Neevo\Result($this->connection, $subquery->as('alias'));
		$this->assertEquals('(SELECT * FROM :foo) :alias', $this->parser($result)->parseSource());
	}


	public function testParseSourceSubqueryAutoAlias(){
		$subquery = new Neevo\Result($this->connection, 'foo');
		$result = new Neevo\Result($this->connection, $subquery);
		$this->assertEquals('(SELECT * FROM :foo) :_table_', $this->parser($result)->parseSource());
	}


	public function testParseSourceJoinSubquery(){
		$subquery = new Neevo\Result($this->connection, 'tab2');
		$result = new Neevo\Result($this->connection, 'tab1');
		$this->assertEquals(
			':tab1 LEFT JOIN (SELECT * FROM :tab2) :tab2 ON :tab1.id = :tab2.tab1_id',
			$this->parser($result->leftJoin($subquery->as('tab2'), ':tab1.id = :tab2.tab1_id'))->parseSource()
		);
	}


	public function testParseSourceJoinSubqueryAutoAlias(){
		$subquery = new Neevo\Result($this->connection, 'tab2');
		$result = new Neevo\Result($this->connection, 'tab1');
		$this->assertEquals(
			':tab1 LEFT JOIN (SELECT * FROM :tab2) :_join_1 ON :tab1.id = :tab2.tab1_id',
			$this->parser($result->leftJoin($subquery, ':tab1.id = :tab2.tab1_id'))->parseSource()
		);
	}


	public function testParseWhereSimpleSubquery(){
		$this->assertEquals(
			" WHERE (:bar IN (SELECT * FROM :foo))",
			$this->parser($this->createSelect()->where('bar', $this->createSelect()))->parseWhere()
		);
	}


}
