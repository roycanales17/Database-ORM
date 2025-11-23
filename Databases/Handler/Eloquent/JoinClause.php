<?php

	namespace App\Databases\Handler\Eloquent;

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

		public function where(string $col, string $operator, mixed $value): self
		{
			$this->conditions[] = "$col $operator ?";
			$this->bindings[] = $value;
			return $this;
		}

		public function orWhere(string $col, string $operator, mixed $value): self
		{
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