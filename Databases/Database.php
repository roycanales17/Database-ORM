<?php

	namespace App\Databases;

	use App\Databases\Facade\Connections;
	use App\Databases\Facade\Driver;
	use App\Databases\Handler\Blueprints\ServerChain;
	use App\Databases\Handler\Blueprints\QueryReturnType;
	use App\Databases\Handler\DatabaseException;
	use App\Databases\Handler\Blueprints\UpdateChain;
	use Exception;

	/**
	 * Class Database
	 *
	 * Provides a high-level API for configuring servers,
	 * running queries, and interacting with tables.
	 *
	 * @package Databases
	 */
	class Database extends Connections
	{
		private static array $activeTransactionDriver = [];

		/**
		 * Register a new database server configuration.
		 *
		 * @param string $server  The server identifier (name).
		 * @param array  $config  The connection configuration (host, user, password, database, etc).
		 *
		 * @throws DatabaseException If the server is already registered.
		 * @return void
		 */
		public static function configure(string $server, array $config): void
		{
			if (!self::isServerExist($server)) {
				self::register($server, $config);
				return;
			}

			throw new DatabaseException("Server '{$server}' is already registered");
		}

		/**
		 * Get an Actions instance for the specified server.
		 *
		 * @param string $connection The server identifier.
		 *
		 * @return ServerChain
		 */
		public static function server(string $connection): ServerChain
		{
			return new ServerChain($connection);
		}

		/**
		 * Start an Eloquent query builder instance for a specific table.
		 *
		 * @param string $table The table name.
		 *
		 * @return Eloquent
		 */
		public static function table(string $table): Eloquent
		{
			$obj = new Eloquent();
			return $obj->table($table);
		}

		/**
		 * Execute a raw SQL query and return results.
		 *
		 * @param string          $query      The SQL query to execute.
		 * @param array           $params     Bound parameters for prepared statement.
		 * @param QueryReturnType $returnType The expected return type (e.g., ALL, FIRST, COUNT).
		 *
		 * @return mixed The query result.
		 */
		public static function query(string $query, array $params = [], QueryReturnType $returnType = QueryReturnType::ALL): mixed
		{
			return self::execute(null, $query, $params, $returnType);
		}

		/**
		 * Insert or update a row in the database using REPLACE.
		 *
		 * @param string $table The table name.
		 * @param array  $data  Associative array of column => value pairs.
		 *
		 * @return mixed The inserted/updated primary key or result.
		 */
		public static function replace(string $table, array $data): mixed
		{
			$obj = new Eloquent();
			$obj->table($table);
			return $obj->replace($data);
		}

		/**
		 * Update rows in the database using a fluent chain builder.
		 *
		 * Example:
		 * ```php
		 * Database::update('users', ['name' => 'John', 'email' => 'john@example.com'])
		 *     ->where('id', 1)
		 *     ->execute();
		 * ```
		 *
		 * @param string $table The table name.
		 * @param array  $data  Associative array of column => value pairs.
		 *
		 * @return UpdateChain Returns an UpdateChain instance for chaining conditions.
		 */
		public static function update(string $table, array $data): UpdateChain
		{
			return new UpdateChain($table, $data);
		}

		/**
		 * Delete rows from the database with given conditions.
		 *
		 * @param string $table      The table name.
		 * @param array  $conditions Associative array of conditions:
		 *                           - ['column' => 'value']
		 *                           - ['column' => ['operator', 'value']]
		 *
		 * @return int Number of affected rows.
		 */
		public static function delete(string $table, array $conditions): int
		{
			$obj = new Eloquent();
			$obj->table($table);

			foreach ($conditions as $column => $value) {
				if (is_array($value)) {
					[$operator, $val] = $value;
					$obj->where($column, $operator, $val);
				} else {
					$obj->where($column, $value);
				}
			}

			return $obj->delete();
		}

		/**
		 * Create a new row in the database.
		 *
		 * @param string $table The table name.
		 * @param array  $data  Associative array of column => value pairs.
		 *
		 * @return int The inserted row ID.
		 */
		public static function create(string $table, array $data): int
		{
			$obj = new Eloquent();
			$obj->table($table);
			return $obj->create($data);
		}

		/**
		 * Begin a new database transaction on the specified server and driver.
		 *
		 * This method starts a new transaction that allows executing multiple
		 * queries as a single atomic operation. Changes made during the transaction
		 * can later be confirmed with `commit()` or discarded with `rollback()`.
		 *
		 * Example:
		 * ```php
		 * Database::beginTransaction('main');
		 * try {
		 *     Database::create('users', ['name' => 'John']);
		 *     Database::update('accounts', ['balance' => 500])->where('id', 1)->execute();
		 *     Database::commit('main');
		 * } catch (Exception $e) {
		 *     Database::rollback('main');
		 *     throw $e;
		 * }
		 * ```
		 *
		 * @param string|null $server  The server identifier (optional).
		 *                             If null, the default or random server will be used.
		 * @param Driver      $driver  The database driver to use (default: `Driver::MYSQLI`).
		 *
		 * @return void
		 */
		public static function beginTransaction(?string $server = null, Driver $driver = Driver::MYSQLI): void
		{
			self::$activeTransactionDriver[$server] = $driver;
			self::execute(self::instance($server, $driver), "START TRANSACTION;");
		}

		/**
		 * Commit the active transaction for the specified server.
		 *
		 * This permanently saves all changes made during the current transaction.
		 * Once committed, the transaction cannot be rolled back.
		 *
		 * Example:
		 * ```php
		 * Database::beginTransaction('main');
		 * Database::update('users', ['active' => true])->where('id', 5)->execute();
		 * Database::commit('main'); // Save all changes
		 * ```
		 *
		 * @param string|null $server  The server identifier (optional).
		 *                             If null, applies to the default or last used connection.
		 *
		 * @return void
		 */
		public static function commit(?string $server = null): void
		{
			$driver = self::$activeTransactionDriver[$server] ?? Driver::MYSQLI;
			self::execute(self::instance($server, $driver), "COMMIT;");
			unset(self::$activeTransactionDriver[$server]);
		}

		/**
		 * Roll back the active transaction for the specified server.
		 *
		 * This undoes all changes made during the current transaction, restoring
		 * the database to its state before `beginTransaction()` was called.
		 *
		 * Example:
		 * ```php
		 * Database::beginTransaction('main');
		 * Database::create('orders', ['user_id' => 1, 'total' => 100]);
		 * Database::rollback('main'); // Undo changes
		 * ```
		 *
		 * @param string|null $server  The server identifier (optional).
		 *                             If null, applies to the default or last used connection.
		 *
		 * @return void
		 */
		public static function rollback(?string $server = null): void
		{
			$driver = self::$activeTransactionDriver[$server] ?? Driver::MYSQLI;
			self::execute(self::instance($server, $driver), "ROLLBACK;");
			unset(self::$activeTransactionDriver[$server]);
		}


		/**
		 * Execute a set of database operations within a transaction.
		 *
		 * This method wraps multiple database operations inside a transaction.
		 * If the callback executes successfully, the transaction is committed.
		 * If an exception occurs, the transaction is rolled back automatically.
		 *
		 * @param callable        $callback The callback containing database operations to execute.
		 * @param string|null     $server   Optional server identifier. If null, the default or last used server is used.
		 * @param Driver          $driver   The database driver to use (default: `Driver::MYSQLI`).
		 *
		 * @throws DatabaseException If any exception occurs during the transaction.
		 * @return mixed Returns whatever the callback returns.
		 */
		public static function transaction(callable $callback, ?string $server = null, Driver $driver = Driver::MYSQLI): mixed
		{
			self::beginTransaction($server, $driver);
			try {
				$result = $callback();
				self::commit($server);
				return $result;
			} catch (Exception $e) {
				self::rollback($server);
				throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
			}
		}
	}