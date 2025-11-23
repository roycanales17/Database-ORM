<?php

	namespace App\Databases\Handler\Eloquent;

	use Closure;

	final class JoinClause
	{
		public array $conditions = [];
		public array $bindings = [];
		public string $type;
		public string $table;

		public function __construct(string $type, string $table)
		{
			$this->type  = $type;
			$this->table = $table;
		}

		public function on(string $first, string $operator, string $second): self
		{
			$this->conditions[] = "$first $operator $second";
			return $this;
		}

		// Updated where to support closure
		public function where(string|Closure $col, string $operator = '', mixed $value = null): self
		{
			if ($col instanceof Closure) {
				$nested = new self($this->type, $this->table);
				$col($nested);
				$this->conditions[] = '(' . implode(' AND ', $nested->conditions) . ')';
				$this->bindings = array_merge($this->bindings, $nested->bindings);
				return $this;
			}

			$this->conditions[] = "$col $operator ?";
			$this->bindings[] = $value;
			return $this;
		}

		public function orWhere(string|Closure $col, string $operator = '', mixed $value = null): self
		{
			// If the first param is a closure, handle it as a nested OR group
			if ($col instanceof Closure) {
				$nested = new self($this->type, $this->table);
				$col($nested);

				// Wrap nested conditions in parentheses and prepend OR
				$this->conditions[] = 'OR (' . implode(' AND ', $nested->conditions) . ')';
				$this->bindings = array_merge($this->bindings, $nested->bindings);

				return $this;
			}

			// Otherwise, normal OR condition
			$this->conditions[] = "OR $col $operator ?";
			$this->bindings[] = $value;

			return $this;
		}

		public function whereRaw(string $sql): self
		{
			$this->conditions[] = $sql;
			return $this;
		}
	}