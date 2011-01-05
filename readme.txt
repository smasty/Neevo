Neevo - Tiny open-source database layer for PHP


Info
====

- Available under the MIT license (http://neevo.smasty.net/license)
- Author: Martin Srank - Smasty (http://smasty.net)
- Website: http://neevo.smasty.net/
- Public API: http://neevo.smasty.net/doc/


About Neevo
===========

First of all, thank you for using Neevo!

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
 - One-file-only minified version
    (Thanks to Jakub Vrana - http://php.vrana.cz and his Adminer - http://adminer.org)


Supported drivers
=================

 - MySQL (PHP extension 'mysql')
 - MySQLi
 - SQLite
 - SQLite 3


Todo
====

 - better test coverage

 - PDO driver
 - PostgreSQL driver


Compiler
========

Neevo comes with a "compiler" - PHP CLI script shrinking whole Neevo to one PHP file.
Included drivers can also be specified.

Usage:
  $ php compiler.php [-d=<drivers>] [-h] [-q]

Options:

  -d=<drivers>  Comma-separated list of drivers to include.
                Defaults to all drivers.
  -h            Displays help.
  -q            Quiet mode - no output.


Minification functions are written by Jakub Vrana (http://php.vrana.cz) for his
Adminer (http://adminer.org) licensed under Apache license 2.0 and are used with
his kind permission.
