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


/**
 * Neevo observer interface.
 * @author Martin Srank
 * @package Neevo
 */
interface INeevoObserver {

	// Event types
	const CONNECT = 2,

		SELECT = 4,
		INSERT = 8,
		UPDATE = 16,
		DELETE = 32,
		QUERY = 60, // SELECT, INSERT, UPDATE, DELETE

		BEGIN = 64,
		COMMIT = 128,
		ROLLBACK = 256,
		TRANSACTION = 448, // BEGIN, COMMIT, ROLLBACK

		EXCEPTION = 512,
		ALL = 1022;


	/**
	 * Receive update from observable.
	 * @param INeevoObservable $observable
	 * @param int $event Event type
	 * @return void
	 */
	public function updateStatus(INeevoObservable $observable, $event);


}
