<?php

	namespace App\Databases;

	use App\Databases\Handler\Blueprints\Table;
	use App\Databases\Handler\Collection;
	use Closure;

	/**
	 * Class Schema
	 *
	 * A lightweight schema management class that provides methods to
	 * create, modify, inspect, and drop database tables and columns dynamically.
	 *
	 * This class acts as a simplified alternative to Laravel's Schema builder,
	 * using raw SQL queries to execute migrations.
	 *
	 * @package App\Databases
	 */
	class Schema
	{
		/**
		 * Create a new table schema.
		 *
		 * Example:
		 * ```php
		 * Schema::create('users', function (Table $table) {
		 *     $table->id();
		 *     $table->string('name', 100);
		 *     $table->timestamps();
		 * });
		 * ```
		 *
		 * @param string $table The table name to create.
		 * @param Closure $callback A callback that defines the table structure.
		 * @return mixed The query result or false if no SQL was generated.
		 */
		public static function create(string $table, Closure $callback)
		{
			$blueprint = new Table($table);
			$callback($blueprint);

			$sql = $blueprint->toSql('create');
			if ($sql) {
				return Database::query($sql);
			}

			return false;
		}

		/**
		 * Modify an existing table (e.g., add new columns).
		 *
		 * Example:
		 * ```php
		 * Schema::table('users', function (Table $table) {
		 *     $table->string('email', 150)->after('name');
		 * });
		 * ```
		 *
		 * @param string $table The table name to modify.
		 * @param Closure $callback A callback that defines alterations to apply.
		 * @return mixed The query result or false if no SQL was generated.
		 */
		public static function table(string $table, Closure $callback)
		{
			$blueprint = new Table($table);
			$callback($blueprint);

			$sql = $blueprint->toSql('alter');
			if ($sql) {
				return Database::query($sql);
			}

			return false;
		}

		/**
		 * Rename an existing table.
		 *
		 * @param string $from The current table name.
		 * @param string $to The new table name.
		 * @return mixed Query result.
		 */
		public static function renameTable(string $from, string $to)
		{
			return Database::query("ALTER TABLE {$from} RENAME TO {$to}");
		}

		/**
		 * Drop a table if it exists.
		 *
		 * @param string $table The table name.
		 * @return mixed Query result.
		 */
		public static function dropIfExists(string $table)
		{
			return Database::query("DROP TABLE IF EXISTS {$table}");
		}

		/**
		 * Check if a table exists in the database.
		 *
		 * @param string $table The table name to check.
		 * @return int 1 if exists, 0 if not.
		 */
		public static function hasTable(string $table): int
		{
			$result = Database::query("SHOW TABLES LIKE '{$table}'");
			$obj = new Collection($result);
			return $obj->count(true);
		}

		/**
		 * Retrieve information for a specific column.
		 *
		 * @param string $table Table name.
		 * @param string $column Column name.
		 * @return array Column information.
		 */
		public static function column(string $table, string $column): array
		{
			$result = Database::query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
			$obj = new Collection($result);
			return $obj->row();
		}

		/**
		 * Retrieve multiple specific columns' information.
		 *
		 * @param string $table Table name.
		 * @param array $columns Array of column names.
		 * @return mixed Query result.
		 */
		public static function columns(string $table, array $columns)
		{
			$cols = implode("','", $columns);
			return Database::query("SHOW COLUMNS FROM {$table} WHERE Field IN ('{$cols}')");
		}

		/**
		 * Fetch all columns of a given table.
		 *
		 * @param string $table Table name.
		 * @return array List of column definitions.
		 */
		public static function fetchColumns(string $table): array
		{
			return Database::query("SHOW COLUMNS FROM `{$table}`");
		}

		/**
		 * Export a table’s structure (CREATE TABLE SQL).
		 *
		 * @param string $table Table name.
		 * @return array Table structure as SQL.
		 */
		public static function exportTable(string $table): array
		{
			$result = Database::query("SHOW CREATE TABLE `{$table}`");
			$obj = new Collection($result);
			return $obj->row();
		}

		/**
		 * Retrieve index information for a specific index.
		 *
		 * @param string $table Table name.
		 * @param string $indexName Index name.
		 * @return mixed Index details.
		 */
		public static function index(string $table, string $indexName): mixed
		{
			$result = Database::query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
			$obj = new Collection($result);
			return $obj->row();
		}

		/**
		 * Drop an entire table.
		 *
		 * @param string $table Table name.
		 * @return mixed Query result.
		 */
		public static function drop(string $table): mixed
		{
			return Database::query("DROP TABLE `{$table}`");
		}

		/**
		 * Drop a specific column from a table.
		 *
		 * @param string $table Table name.
		 * @param string $column Column name.
		 * @return mixed Query result.
		 */
		public static function dropColumn(string $table, string $column): mixed
		{
			return Database::query("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
		}

		/**
		 * Rename a column in a table.
		 *
		 * @param string $table Table name.
		 * @param string $from Old column name.
		 * @param string $to New column name.
		 * @param string $type Column data type (e.g. VARCHAR(255)).
		 * @return mixed SQL string or query result.
		 */
		public static function renameColumn(string $table, string $from, string $to, string $type): mixed
		{
			return Database::query("ALTER TABLE `{$table}` CHANGE `{$from}` `{$to}` {$type}");
		}

		/**
		 * Add an index to a column.
		 *
		 * @param string $table Table name.
		 * @param string $column Column name.
		 * @param string|null $indexName Optional index name.
		 * @return mixed Query result.
		 */
		public static function addIndex(string $table, string $column, string $indexName = null): mixed
		{
			$indexName = $indexName ?? "{$column}_index";
			return Database::query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
		}

		/**
		 * Drop an index from a table.
		 *
		 * @param string $table Table name.
		 * @param string $indexName Index name.
		 * @return mixed Query result.
		 */
		public static function dropIndex(string $table, string $indexName): mixed
		{
			return Database::query("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
		}

		/**
		 * Change the storage engine for a table.
		 *
		 * @param string $table Table name.
		 * @param string $engine New engine (e.g. InnoDB, MyISAM).
		 * @return mixed Query result.
		 */
		public static function setEngine(string $table, string $engine): mixed
		{
			return Database::query("ALTER TABLE `{$table}` ENGINE={$engine}");
		}

		/**
		 * Set the default character set (and optional collation) for a table.
		 *
		 * @param string $table Table name.
		 * @param string $charset Charset (e.g. utf8mb4).
		 * @param string|null $collation Optional collation (e.g. utf8mb4_unicode_ci).
		 * @return mixed Query result.
		 */
		public static function setCharset(string $table, string $charset, string $collation = null): mixed
		{
			$sql = "ALTER TABLE `{$table}` DEFAULT CHARSET={$charset}";
			if ($collation) {
				$sql .= " COLLATE={$collation}";
			}
			return Database::query($sql);
		}

		/**
		 * Truncate a table (delete all rows and reset AUTO_INCREMENT).
		 *
		 * @param string $table Table name.
		 * @return mixed Query result.
		 */
		public static function truncate(string $table): mixed
		{
			return Database::query("TRUNCATE TABLE `{$table}`");
		}

		/**
		 * Reset a table by deleting all data while preserving structure.
		 *
		 * This is an alias for {@see Schema::truncate()}.
		 *
		 * Example:
		 * ```php
		 * Schema::reset('project_requests');
		 * ```
		 *
		 * @param string $table Table name.
		 * @return mixed Query result.
		 */
		public static function reset(string $table): mixed
		{
			return self::truncate($table);
		}
	}
