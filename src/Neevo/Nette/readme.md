This part provides tools to optimize experience when using Neevo along
with Nette Framework. It will register Neevo as a service to a DI Container.
It will add a panel to DebugBar showing performed queries and a panel to
Bluescreen with SQL query in case of NeevoException.

Only Nette Framework 2.0 and above PHP 5.3 packages are supported.

Instructions
============

1.  In your Nette Framework config file (e.g. %appDir%/config.neon),
    in "services" section, add the following service definition:

    services:
        ...
        neevo: Neevo\Nette\Factory::createService(@cacheStorage, %database%)


2.  In the "parameters" section, add another section called "database".
    That is the place for all your Neevo configuration, for example:

    database:
        driver: MySQLi
        username: root
        password: ****
        database: my_database
		explain: yes

    'explain' option denotes whether or not you want to run EXPLAIN on all
    performed SELECT queries for debugging purposes. Defaults to 'yes'.