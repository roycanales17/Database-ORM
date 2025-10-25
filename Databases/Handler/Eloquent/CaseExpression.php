<?php
	namespace App\Databases\Handler\Eloquent;

	/**
	 * Build a SQL CASE expression.
	 *
	 * Example:
	 *   $builder->case()
	 *       ->when('status = 1', "'Active'")
	 *       ->when('status = 0', "'Inactive'")
	 *       ->else("'Unknown'")
	 *       ->end('status_label');
	 */
	class CaseExpression
	{
		protected array $cases = [];
		protected ?string $else = null;
		protected ?string $alias = null;

		public function when(string $condition, string $result): self
		{
			$this->cases[] = "WHEN {$condition} THEN {$result}";
			return $this;
		}

		public function else(string $result): self
		{
			$this->else = "ELSE {$result}";
			return $this;
		}

		public function end(?string $alias = null): string
		{
			$this->alias = $alias;
			$case = "CASE " . implode(' ', $this->cases);
			if ($this->else) {
				$case .= " {$this->else}";
			}
			$case .= " END";
			if ($this->alias) {
				$case .= " AS {$this->alias}";
			}
			return $case;
		}
	}
