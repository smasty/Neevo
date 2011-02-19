Neevo - Tiny open-source database layer for PHP


Info
====

- Available under the MIT license (http://neevo.smasty.net/license)
- Author: Martin Srank - Smasty (http://smasty.net)
- Website: http://neevo.smasty.net/
- Public API: http://neevo.smasty.net/api/


About Neevo
===========

Neevo is a very small, fully object-oriented database abstraction layer for PHP.
It's open-source and released under the terms and conditions of the MIT license.

Neevo allows you to easily write SQL queries for different SQL drivers
in unified syntax with the use of Object-oriented PHP and fluent interfaces.
Of course, Neevo automatically escapes all code to avoid SQL Injection attacs, etc.

Neevo currently supports four drivers: MySQL, MySQLi, SQLite and SQLite 3.
Neevo also offers an Interface and Public API for other programmers, so new drivers
can be easily added.


Features
========

 - Easy and intuitive manipulation
 - SELECT, INSERT, UPDATE, DELTE queires (with JOIN support)
 - Transaction support
 - Multiple drivers support
 - More ways to fetch your data: as objects, arrays, key=>value pairs, single row...
 - Dump queries
 - Query debugging
 - Conditional statements
 - Column type detection
 - One-file-only minified version
    (Thanks to Jakub Vrana - http://php.vrana.cz and his Adminer - http://adminer.org)


Supported drivers
=================

 - MySQL
 - MySQLi
 - SQLite
 - SQLite 3
 