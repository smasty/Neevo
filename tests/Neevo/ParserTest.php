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

namespace Neevo\Test;

use DateTime;
use Neevo\BaseStatement;
use Neevo\Connection;
use Neevo\Drivers\DummyParserDriver;
use Neevo\Literal;
use Neevo\Manager;
use Neevo\Result;
use Neevo\Statement;


class ParserTest extends \PHPUnit_Framework_TestCase {


	/** @var Connection */
	private $connection;


	protected function setUp(){
		$this->connection = new Connection('driver=DummyParser');
	}


	protected function tearDown(){
		unset($this->connection);
	}


	/** @return DummyParserDriver */
	private function parser(BaseStatement $stmt){
		$instance = $this->connection->getParser();
		return new $instance($stmt);
	}


	private function createSelect(){
		return new Result($this->connection, 'foo');
	}


	private function createInsert($table, array $values){
		return Statement::createInsert($this->connection, $table, $values);
	}


	private function createUpdate($table, array $data){
		return Statement::createUpdate($this->connection, $table, $data);
	}


	private function createDelete($table){
		return Statement::createDelete($this->connection, $table);
	}


	public function parseWhere(){
		$date = new DateTime;
		return array(
			array(array('bar', null), "WHERE (`bar` IS NULL)"),
			array(array('bar', true), "WHERE (`bar`)"),
			array(array('bar', false), "WHERE (NOT `bar`)"),
			array(array('bar', array(1, 2)), "WHERE (`bar` IN (1, 2))"),
			array(array('bar', new Literal('NOW()')), "WHERE (`bar` = NOW())"),
			array(array('bar', $date), "WHERE (`bar` = '{$date->format('Y-m-d H:i:s')}')"),
			array(array('bar', 'baz'), "WHERE (`bar` = 'baz')"),
			array(array(':bar = %i AND :baz = %s', 1, 2), "WHERE (`bar` = 1 AND `baz` = '2')")
		);
	}


	/**
	 * @dataProvider parseWhere
	 */
	public function testParseWhere($inputParams, $output){
		$this->assertEquals(
			$output,
			trim($this->parser(call_user_func_array(array($this->createSelect(), 'where'), $inputParams))
			->parseWhere())
		);
	}


	public function testParseWhereSimpleGlue(){
		$this->assertEquals(
			"WHERE (`bar`) AND (`baz`)",
			trim($this->parser($this->createSelect()->where('bar')->and('baz'))->parseWhere())
		);
	}


	public function testParseWhereModifiersGlue(){
		$this->assertEquals(
			"WHERE (`bar` = 1, `baz` = 2) AND (`bar`)",
			trim($this->parser($this->createSelect()->where(':bar = %i, :baz = %i', 1, 2)->and(':bar'))
			->parseWhere())
		);
	}


	public function testParseSorting(){
		$this->assertEquals(
			"ORDER BY `bar`, `baz` DESC",
			trim($this->parser($this->createSelect()->order(':bar')->order(':baz', Manager::DESC))
			->parseSorting())
		);
	}


	public function testParseGrouping(){
		$this->assertEquals(
			"GROUP BY `bar` HAVING `baz`",
			trim($this->parser($this->createSelect()->group(':bar', ':baz'))->parseGrouping())
		);
	}


	public function testParseSource(){
		$this->assertEquals(
			"`foo`",
			$this->parser($this->createInsert('foo', (array) 1))->parseSource()
		);
		$this->assertEquals(
			"`foo`",
			$this->parser($this->createSelect())->parseSource()
		);
	}


	public function testParseSourceJoin(){
		$this->assertEquals(
			"`foo`\nLEFT JOIN `bar` ON `bar.id` = `foo.bar_id`",
			$this->parser($this->createSelect()->leftJoin(':bar', ':bar.id = :foo.bar_id'))->parseSource()
		);
	}


	public function testParseSourceJoinLiteral(){
		$this->assertEquals(
			"`foo`\nLEFT JOIN GETTABLE(bar) ON bar.id = foo.bar_id",
			$this->parser($this->createSelect()
				->leftJoin(new Literal('GETTABLE(bar)'), new Literal('bar.id = foo.bar_id')))
			->parseSource()
		);
	}


	public function testApplyLimit(){
		$this->assertEquals(
			"foo\nLIMIT 15 OFFSET 5",
			$this->parser($this->createSelect()->limit(15, 5))->applyLimit('foo')
		);
	}


	public function parseFieldName(){
		return array(
			array(':foo', '`foo`'),
			array(new Literal('NOW()'), 'NOW()'),
			array('*', '*'),
			array('foo bar', 'foo bar'),
			array(':foo.bar', '`foo.bar`'),
			array(':fooBar', '`fooBar`'),
		);
	}


	/**
	 * @dataProvider parseFieldName
	 */
	public function testParseFieldName($input, $output){
		$this->assertEquals(
			$output,
			$this->parser($this->createSelect())->tryDelimite($input)
		);
	}


	public function escapeValueNoType(){
		$date = new DateTime;
		return array(
			array(null, 'NULL'),
			array(array('1', 'foo'), array(1, "'foo'")),
			array('1', 1),
			array('-1', -1),
			array('foo', "'foo'"),
			array(new Literal(1), 1),
			array($date, $date->format("'Y-m-d H:i:s'"))
		);
	}


	/**
	 * @dataProvider escapeValueNoType
	 */
	public function testEscapeValue($input, $output){
		$this->assertTrue(
			$output === $this->parser($this->createSelect())->escapeValue($input)
		);
	}


	public function escapeValueType(){
		return array(
			array(array('1', 'foo'), array(Manager::FLOAT, Manager::TEXT), array(1.0, "'foo'")),
			array('1', Manager::INT, 1),
			array('1', Manager::FLOAT, 1.0),
			array(array(1, 'foo'), Manager::ARR, "(1, 'foo')"),
			array('1', Manager::LITERAL, '1'),
			array('foo', Manager::BINARY, "bin:'foo'")
		);
	}


	/**
	 * @dataProvider escapeValueType
	 */
	public function testEscapeValueType($input, $type, $output){
		$this->assertTrue(
			$output === $this->parser($this->createSelect())->escapeValue($input, $type)
		);
	}


	public function testEscapeValueUnknownType(){
		$this->setExpectedException('InvalidArgumentException');
		$this->parser($this->createSelect())->escapeValue('foo', 'unknown_type');
	}


	public function testTryDelimiteLiteral(){
		$this->assertEquals('NOW()',
			$this->parser($this->createSelect())->tryDelimite(new Literal('NOW()'))
		);
	}


	public function testParseSelect(){
		$this->assertEquals(
			"SELECT *\nFROM `foo`\nWHERE (`id` = 5)\nGROUP BY `id`\nORDER BY `id`",
			trim($this->parser($this->createSelect()->where(':id', 5)->group(':id')->order(':id'))->parse())
		);
	}


	public function testParseInsert(){
		$this->assertEquals(
			"INSERT INTO `table` (`id`, `name`)\nVALUES (5, 'John Doe')",
			$this->parser($this->createInsert(':table', array(
					'id' => 5,
					'name' => 'John Doe'
				)))->parse()
		);
	}


	public function testParseUpdate(){
		$this->assertEquals(
			"UPDATE `table`\nSET `id` = 5, `name` = 'John Doe'\nWHERE (`id` = 5)",
			$this->parser($this->createUpdate(':table', array(
					'id' => 5,
					'name' => 'John Doe'
				))->where(':id', 5))->parse()
		);
	}


	public function testParseDelete(){
		$this->assertEquals(
			"DELETE FROM `table`\nWHERE (`id` = 5)",
			$this->parser($this->createDelete(':table')->where(':id', 5))->parse()
		);
	}


	public function testParseSourceSubquery(){
		$subquery = new Result($this->connection, 'foo');
		$result = new Result($this->connection, $subquery->as('alias'));
		$this->assertEquals(
			"(\n\tSELECT *\n\tFROM `foo`\n) `alias`",
			$this->parser($result)->parseSource()
		);
	}


	public function testParseSourceSubqueryAutoAlias(){
		$subquery = new Result($this->connection, 'foo');
		$result = new Result($this->connection, $subquery);
		$this->assertEquals(
			"(\n\tSELECT *\n\tFROM `foo`\n) `_table_`",
			$this->parser($result)->parseSource()
		);
	}


	public function testParseSourceJoinSubquery(){
		$subquery = new Result($this->connection, 'tab2');
		$result = new Result($this->connection, 'tab1');
		$this->assertEquals(
			"`tab1`\nLEFT JOIN (\n\tSELECT *\n\tFROM `tab2`\n) `tab2` ON `tab1.id` = `tab2.tab1_id`",
			$this->parser($result->leftJoin($subquery->as('tab2'), ':tab1.id = :tab2.tab1_id'))->parseSource()
		);
	}


	public function testParseSourceJoinSubqueryAutoAlias(){
		$sq1 = new Result($this->connection, 'tab2');
		$sq2 = new Result($this->connection, 'tab3');
		$result = new Result($this->connection, 'tab1');
		$this->assertEquals(
			"`tab1`\nLEFT JOIN (\n\tSELECT *\n\tFROM `tab2`\n) `_join_1` ON `foo`"
			. "\nLEFT JOIN (\n\tSELECT *\n\tFROM `tab3`\n) `_join_2` ON `bar`",
			$this->parser($result->leftJoin($sq1, ':foo')->leftJoin($sq2, ':bar'))->parseSource()
		);
	}


	public function testParseWhereSimpleSubquery(){
		$this->assertEquals(
			"\nWHERE (`bar` IN (\n\tSELECT *\n\tFROM `foo`\n))",
			$this->parser($this->createSelect()->where('bar', $this->createSelect()))->parseWhere()
		);
	}


}
