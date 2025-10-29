<?php

	namespace App\Databases\Handler\Blueprints;

	/**
	 * Class Table
	 *
	 * A lightweight schema builder for defining table structures dynamically.
	 * Supports chained syntax for defining columns, defaults, constraints, and indexes.
	 */
	class Table
	{
		protected string $table;
		protected array $columns = [];
		protected array $options = [];
		protected ?string $lastColumn = null;

		/**
		 * Initialize a new Table blueprint for a specific table.
		 */
		public function __construct(string $table)
		{
			$this->table = $table;
		}

		/**
		 * Helper: Define a column safely (nullable by default).
		 */
		private function defineColumn(string $name, string $type): void
		{
			$this->columns[$name] = "`{$name}` {$type} NULL";
			$this->lastColumn = $name;
		}

		/**
		 * Define an auto-incrementing primary key column.
		 */
		public function id(string $name = 'id', int $startingIndex = 0, ?int $length = null): static
		{
			$column = $length
				? "`{$name}` INT({$length}) UNSIGNED AUTO_INCREMENT PRIMARY KEY"
				: "`{$name}` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY";

			$this->columns[$name] = $column;
			$this->lastColumn = $name;

			if ($startingIndex > 0) {
				$this->options['AUTO_INCREMENT'] = $startingIndex;
			}

			return $this;
		}

		/**
		 * Define a VARCHAR column.
		 */
		public function string(string $name, int $length = 255): static
		{
			$this->defineColumn($name, "VARCHAR({$length})");
			return $this;
		}

		/**
		 * Define a TEXT column.
		 */
		public function text(string $name): static
		{
			$this->defineColumn($name, "TEXT");
			return $this;
		}

		/**
		 * Define an INT column.
		 */
		public function integer(string $name): static
		{
			$this->defineColumn($name, "INT");
			return $this;
		}

		/**
		 * Define a DECIMAL column.
		 */
		public function decimal(string $name, int $precision = 8, int $scale = 2): static
		{
			$this->defineColumn($name, "DECIMAL({$precision},{$scale})");
			return $this;
		}

		/**
		 * Define a BOOLEAN column.
		 */
		public function boolean(string $name): static
		{
			$this->defineColumn($name, "TINYINT(1)");
			return $this;
		}

		/**
		 * Define a TIMESTAMP column.
		 */
		public function timestamp(string $name): static
		{
			$this->defineColumn($name, "TIMESTAMP");
			return $this;
		}

		/**
		 * Define a DATETIME column.
		 */
		public function datetime(string $name): static
		{
			$this->defineColumn($name, "DATETIME");
			return $this;
		}

		/**
		 * Define created_at and updated_at timestamp columns.
		 */
		public function timestamps(): static
		{
			$this->columns['created_at'] = "`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP";
			$this->columns['updated_at'] = "`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
			return $this;
		}

		/**
		 * Define an ENUM column.
		 */
		public function enum(string $name, array $values): static
		{
			$escaped = array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $values);
			$enumList = implode(',', $escaped);
			$this->defineColumn($name, "ENUM({$enumList})");
			return $this;
		}

		/**
		 * Add a UNIQUE index for the given column.
		 */
		public function unique(string $column): static
		{
			$this->columns["unique_{$column}"] = "UNIQUE (`{$column}`)";
			return $this;
		}

		/**
		 * Add a regular INDEX for the given column.
		 */
		public function index(string $column, ?string $indexName = null): static
		{
			$indexName ??= "index_{$column}";
			$this->columns[$indexName] = "INDEX `{$indexName}` (`{$column}`)";
			return $this;
		}

		/**
		 * Add a PRIMARY key for an existing column (non-auto-increment).
		 */
		public function primary(string $column): static
		{
			$this->columns["primary_{$column}"] = "PRIMARY KEY (`{$column}`)";
			return $this;
		}

		/**
		 * Add a DEFAULT value to the last defined column.
		 */
		public function default(mixed $value): static
		{
			if ($this->lastColumn && isset($this->columns[$this->lastColumn])) {
				if (is_null($value) || strtoupper((string) $value) === 'NULL') {
					$this->columns[$this->lastColumn] .= " DEFAULT NULL";
				} elseif (is_numeric($value)) {
					$this->columns[$this->lastColumn] .= " DEFAULT {$value}";
				} else {
					$escaped = str_replace("'", "''", (string) $value);
					$this->columns[$this->lastColumn] .= " DEFAULT '{$escaped}'";
				}
			}
			return $this;
		}

		/**
		 * Set DEFAULT CURRENT_TIMESTAMP for the last defined column.
		 */
		public function defaultNow(): static
		{
			if ($this->lastColumn && isset($this->columns[$this->lastColumn])) {
				$this->columns[$this->lastColumn] .= " DEFAULT CURRENT_TIMESTAMP";
			}
			return $this;
		}

		/**
		 * Set ON UPDATE CURRENT_TIMESTAMP for the last defined column.
		 */
		public function updateNow(): static
		{
			if ($this->lastColumn && isset($this->columns[$this->lastColumn])) {
				$this->columns[$this->lastColumn] .= " ON UPDATE CURRENT_TIMESTAMP";
			}
			return $this;
		}

		/**
		 * Allow the column to contain NULL values.
		 */
		public function nullable(): static
		{
			if ($this->lastColumn && isset($this->columns[$this->lastColumn])) {
				if (!str_contains($this->columns[$this->lastColumn], 'NULL')) {
					$this->columns[$this->lastColumn] .= " NULL";
				}
			}
			return $this;
		}

		/**
		 * Enforce the column to be NOT NULL.
		 */
		public function notNull(): static
		{
			if ($this->lastColumn && isset($this->columns[$this->lastColumn])) {
				// Replace NULL with NOT NULL if already exists
				$this->columns[$this->lastColumn] = preg_replace('/\bNULL\b/', '', $this->columns[$this->lastColumn]);
				$this->columns[$this->lastColumn] .= " NOT NULL";
			}
			return $this;
		}

		/**
		 * Compile the schema definition to SQL.
		 */
		public function toSql(string $type): string
		{
			if ($type === 'create') {
				$cols = implode(", ", $this->columns);
				$sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` ({$cols})";

				if (!empty($this->options)) {
					foreach ($this->options as $key => $val) {
						$sql .= " {$key}={$val}";
					}
				}

				return $sql . ";";
			}

			if ($type === 'alter') {
				$cols = implode(", ", array_map(fn($col) => "ADD {$col}", $this->columns));
				return "ALTER TABLE `{$this->table}` {$cols};";
			}

			return '';
		}
	}
