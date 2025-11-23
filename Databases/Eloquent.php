<?php

	namespace App\Databases;

	use Closure;
	use App\Databases\Handler\Eloquent\Builder;

	/**
	 * Class Eloquent
	 *
	 * A query builder for constructing SQL queries with a fluent API,
	 * supporting nested conditions, subqueries, ordering, limits, and offsets.
	 *
	 * @package Databases
	 */
	class Eloquent extends Builder
	{
		/**
		 * Create a new Eloquent query builder instance.
		 *
		 * @param string $server The database server identifier (default: "master").
		 */
		public function __construct(string $server = 'master') {
			$this->server = $server;
		}

		/**
		 * Set the table to query.
		 *
		 * @param string $table The table name.
		 * @return $this
		 */
		public function table(string $table): self {
			$this->table = $table;
			return $this;
		}

		/**
		 * Specify columns to select in the query.
		 *
		 * @param string|Closure ...$columns List of column names or closures for sub-selects.
		 * @return $this
		 */
		public function select(string|Closure ...$columns): self {
			$this->columns = $columns ?: ['*'];
			return $this;
		}

		/**
		 * Add a raw SQL SELECT expression.
		 *
		 * Example:
		 * ```php
		 * $query->selectRaw('COUNT(*) AS total');
		 * $query->selectRaw('users.*, NOW() AS current_time');
		 * $query->selectRaw('SUM(amount) as total_amount, AVG(amount) as avg_amount');
		 * ```
		 *
		 * @param string $expression The raw SQL select expression.
		 * @param array $bindings Optional bindings for parameterized expressions.
		 * @return $this
		 */
		public function selectRaw(string $expression, array $bindings = []): self
		{
			$this->columns[] = $expression;

			if (!empty($bindings)) {
				$this->bindings = array_merge($this->bindings, $bindings);
			}

			return $this;
		}

		/**
		 * Add a column/value pair for the UPDATE statement.
		 *
		 * Example:
		 * ```php
		 * $query->table('users')
		 *       ->set('name', 'John')
		 *       ->set('email', 'john@example.com');
		 * ```
		 *
		 * @param string $column
		 * @param mixed $value
		 * @return $this
		 */
		public function set(string $column, mixed $value): self
		{
			$this->sets[$column] = '?';
			$this->setBindings[] = $value;

			return $this;
		}

		/**
		 * Add a WHERE condition to the query.
		 *
		 * Supports closures for nested conditions:
		 * ```php
		 * $query->where(function($q) {
		 *     $q->where('age', '>', 18)->orWhere('status', 'active');
		 * });
		 * ```
		 *
		 * @param string|Closure $col             Column name or closure for nested where.
		 * @param mixed          $OperatorOrValue Operator or value depending on usage.
		 * @param mixed          $value           Value (optional if operator omitted).
		 * @return $this
		 */
		public function where(string|Closure $col, mixed $OperatorOrValue = null, mixed $value = self::EMPTY): self {
			if ($col instanceof Closure) {
				$nested = new self($this->server);
				$col($nested);
				$this->wheres[] = ['nested', $nested->wheres, 'AND'];
				$this->bindings = array_merge($this->bindings, $nested->bindings);
				return $this;
			}

			if ($value === self::EMPTY) {
				$this->wheres[] = ["$col = ?", 'AND'];
				$this->bindings[] = $OperatorOrValue;
			} else {
				$this->wheres[] = ["$col $OperatorOrValue ?", 'AND'];
				$this->bindings[] = $value;
			}

			return $this;
		}

		/**
		 * Add an OR WHERE condition to the query.
		 *
		 * @param string|Closure $col             Column name or closure for nested where.
		 * @param mixed          $OperatorOrValue Operator or value depending on usage.
		 * @param mixed          $value           Value (optional if operator omitted).
		 * @return $this
		 */
		public function orWhere(string|Closure $col, mixed $OperatorOrValue = '', mixed $value = self::EMPTY): self {
			if ($col instanceof Closure) {
				$nested = new self($this->server);
				$col($nested);
				$this->wheres[] = ['nested', $nested->wheres, 'OR'];
				$this->bindings = array_merge($this->bindings, $nested->bindings);
				return $this;
			}

			if ($value === self::EMPTY) {
				$this->wheres[] = ["$col = ?", 'OR'];
				$this->bindings[] = $OperatorOrValue;
			} else {
				$this->wheres[] = ["$col $OperatorOrValue ?", 'OR'];
				$this->bindings[] = $value;
			}

			return $this;
		}

		/**
		 * Add a raw SQL WHERE condition to the query.
		 *
		 * Example:
		 * ```php
		 * $query->whereRaw('reset_expires > NOW()');
		 * $query->whereRaw('created_at BETWEEN ? AND ?', [$start, $end]);
		 * ```
		 *
		 * @param string $expression The raw SQL expression.
		 * @param array $bindings Optional bindings to safely parameterize parts of the raw SQL.
		 * @param string $boolean Boolean operator (AND/OR).
		 * @return $this
		 */
		public function whereRaw(string $expression, array $bindings = [], string $boolean = 'AND'): self
		{
			$this->wheres[] = [$expression, $boolean];
			if (!empty($bindings)) {
				$this->bindings = array_merge($this->bindings, $bindings);
			}
			return $this;
		}

		/**
		 * Add a WHERE condition comparing two columns.
		 *
		 * @param string $first    The first column.
		 * @param string $operator The comparison operator (=, !=, <, >, etc.).
		 * @param string $second   The second column.
		 * @param string $boolean  Boolean operator (AND/OR).
		 * @return $this
		 */
		public function whereColumn(string $first, string $operator, string $second, string $boolean = 'AND'): self
		{
			$this->wheres[] = ["$first $operator $second", $boolean];
			return $this;
		}

		/**
		 * Add an OR WHERE column comparison condition.
		 *
		 * @param string $first    The first column.
		 * @param string $operator The comparison operator.
		 * @param string $second   The second column.
		 * @return $this
		 */
		public function orWhereColumn(string $first, string $operator, string $second): self
		{
			return $this->whereColumn($first, $operator, $second, 'OR');
		}

		/**
		 * Add a WHERE condition with a subquery.
		 *
		 * Example:
		 * ```php
		 * $query->whereSub('users.id', 'IN', function($q) {
		 *     $q->table('orders')->select('user_id')->where('status', 'active');
		 * });
		 * ```
		 *
		 * @param string  $column   The column to compare.
		 * @param string  $operator The operator (=, IN, etc.).
		 * @param Closure $callback The subquery callback.
		 * @param string  $boolean  Boolean operator (AND/OR).
		 * @return $this
		 */
		public function whereSub(string $column, string $operator, Closure $callback, string $boolean = 'AND'): self
		{
			$sub = new self($this->server);
			$callback($sub);

			$subSql = "({$sub->rawSQL(false)})";
			$this->wheres[] = ["$column $operator $subSql", $boolean];
			$this->bindings = array_merge($this->bindings, $sub->bindings);

			return $this;
		}

		/**
		 * Add an OR WHERE condition with a subquery.
		 *
		 * @param string  $column   The column to compare.
		 * @param string  $operator The operator (=, IN, etc.).
		 * @param Closure $callback The subquery callback.
		 * @return $this
		 */
		public function orWhereSub(string $column, string $operator, Closure $callback): self
		{
			return $this->whereSub($column, $operator, $callback, 'OR');
		}

		/**
		 * Add an ORDER BY clause to the query.
		 *
		 * @param string $column The column to sort by.
		 * @param string $sort   Sort direction (ASC/DESC).
		 * @return $this
		 */
		public function orderBy(string $column, string $sort = 'ASC'): self {
			$this->orders[] = "$column $sort";
			return $this;
		}

		/**
		 * Add a LIMIT clause to the query.
		 *
		 * @param int $limit The maximum number of rows.
		 * @return $this
		 */
		public function limit(int $limit): self {
			$this->limit = $limit;
			return $this;
		}

		/**
		 * Add an OFFSET clause to the query.
		 *
		 * @param int $offset Number of rows to skip.
		 * @return $this
		 */
		public function offset(int $offset): self {
			$this->offset = $offset;
			return $this;
		}

		/**
		 * Add a GROUP BY clause to the query.
		 *
		 * Example:
		 * ```php
		 * $query->groupBy('category_id');
		 * $query->groupBy('category_id', 'status');
		 * ```
		 *
		 * @param string ...$columns One or more column names to group by.
		 * @return $this
		 */
		public function groupBy(string ...$columns): self
		{
			$this->groups = array_merge($this->groups, $columns);
			return $this;
		}

		/**
		 * Add a HAVING clause to the query.
		 *
		 * Example:
		 * ```php
		 * $query->groupBy('category_id')
		 *       ->having('COUNT(id)', '>', 5);
		 * ```
		 *
		 * @param string $column   The column or aggregate function.
		 * @param string $operator The comparison operator (=, >, <, etc.).
		 * @param mixed  $value    The value to compare against.
		 * @return $this
		 */
		public function having(string $column, string $operator, mixed $value): self
		{
			$this->havings[] = "$column $operator ?";
			$this->bindings[] = $value;
			return $this;
		}

		/**
		 * Add a raw HAVING clause to the query.
		 *
		 * Example:
		 * ```php
		 * $query->havingRaw('SUM(amount) > ?', [100]);
		 * $query->havingRaw('AVG(score) >= 90');
		 * ```
		 *
		 * @param string $expression Raw SQL HAVING expression.
		 * @param array  $bindings   Optional bound parameters for the expression.
		 * @return $this
		 */
		public function havingRaw(string $expression, array $bindings = []): self
		{
			$this->havings[] = $expression;
			if (!empty($bindings)) {
				$this->bindings = array_merge($this->bindings, $bindings);
			}
			return $this;
		}

		/**
		 * Add an INNER JOIN clause to the query.
		 *
		 * Example:
		 * ```php
		 * $query->join('profiles', 'users.id', '=', 'profiles.user_id');
		 * ```
		 *
		 * @param string $table The name of the table to join.
		 * @param string $first The left-hand column for the join condition.
		 * @param string $operator The comparison operator (e.g. '=', '<>', '!=')
		 * @param string $second The right-hand column for the join condition.
		 * @return $this
		 */
		public function join(string $table, string $first, string $operator, string $second): self
		{
			$this->joins[] = "INNER JOIN {$table} ON {$first} {$operator} {$second}";
			return $this;
		}

		/**
		 * Add a LEFT JOIN clause to the query.
		 *
		 * @param string $table
		 * @param string|Closure $first
		 * @param string $operator
		 * @param string $second
		 * @return $this
		 */
		public function leftJoin(string $table, string|Closure $first, string $operator = '', string $second = ''): self
		{
			if ($first instanceof Closure) {
				$temp = new self($this->server);
				$first($temp);

				// Use buildWhere on the temporary query but remove the leading "WHERE "
				$onClause = $temp->buildWhere();
				if (str_starts_with($onClause, 'WHERE ')) {
					$onClause = substr($onClause, 6);
				}

				$this->joins[] = "LEFT JOIN {$table} ON {$onClause}";
				$this->bindings = array_merge($this->bindings, $temp->bindings);
				return $this;
			}

			// Standard join syntax
			$this->joins[] = "LEFT JOIN {$table} ON {$first} {$operator} {$second}";
			return $this;
		}

		/**
		 * Add a RIGHT JOIN clause to the query.
		 *
		 * @param string $table
		 * @param string|Closure $first
		 * @param string $operator
		 * @param string $second
		 * @return $this
		 */
		public function rightJoin(string $table, string|Closure $first, string $operator = '', string $second = ''): self
		{
			if ($first instanceof Closure) {
				$temp = new self($this->server);
				$first($temp);

				// Build the ON clause using buildWhere
				$onClause = $temp->buildWhere();
				if (str_starts_with($onClause, 'WHERE ')) {
					$onClause = substr($onClause, 6);
				}

				$this->joins[] = "RIGHT JOIN {$table} ON {$onClause}";
				$this->bindings = array_merge($this->bindings, $temp->bindings);
				return $this;
			}

			// Standard join syntax
			$this->joins[] = "RIGHT JOIN {$table} ON {$first} {$operator} {$second}";
			return $this;
		}

		/**
		 * Conditionally execute a callback on the query.
		 *
		 * Runs $callback if $condition is truthy,
		 * otherwise runs $default if provided. Null and false are falsy; other values are truthy.
		 *
		 * @param mixed $condition Condition to evaluate.
		 * @param Closure $callback Callback executed if the condition is truthy.
		 * @param Closure|null $default Optional callback executed if condition is falsy.
		 * @return $this
		 */
		public function when(mixed $condition, Closure $callback, ?Closure $default = null): self
		{
			// Consider null or false as falsy
			$isTruthy = $condition !== null && $condition !== false;

			if ($isTruthy) {
				$callback($this, $condition);
			} elseif ($default) {
				$default($this, $condition);
			}

			return $this;
		}

		/**
		 * Add an ON clause for joins.
		 *
		 * Example:
		 * ```php
		 * $query->leftJoin('profiles', function($join) {
		 *     $join->on('users.id', '=', 'profiles.user_id')
		 *          ->where('profiles.active', 1);
		 * });
		 * ```
		 *
		 * @param string $first The first column.
		 * @param string $operator The operator (default '=')
		 * @param string $second The second column.
		 * @return $this
		 */
		public function on(string $first, string $operator = '=', string $second = ''): self
		{
			if ($second === '') {
				$second = $operator;
				$operator = '=';
			}

			$this->wheres[] = "$first $operator $second";
			return $this;
		}
	}