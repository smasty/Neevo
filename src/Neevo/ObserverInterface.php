<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2013 Smasty (http://smasty.net)
 *
 */

namespace Neevo;


/**
 * Neevo observer interface.
 * @author Smasty
 */
interface ObserverInterface
{


    // Event types

    const CONNECT = 2;
    const SELECT = 4;
    const INSERT = 8;
    const UPDATE = 16;
    const DELETE = 32;
    const QUERY = 60; // SELECT, INSERT, UPDATE, DELETE

    const BEGIN = 64;
    const COMMIT = 128;
    const ROLLBACK = 256;
    const TRANSACTION = 448; // BEGIN, COMMIT, ROLLBACK

    const EXCEPTION = 512;
    const DISCONNECT = 1024;
    const ALL = 2046;


    /**
     * Receives update from observable subject.
     * @param ObservableInterface $subject
     * @param int $event Event type
     */
    public function updateStatus(ObservableInterface $subject, $event);
}
