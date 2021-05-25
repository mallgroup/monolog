<?php
declare(strict_types=1);

namespace Mallgroup\Monolog\DI;


use Nette\DI\Definitions\Definition;

class PriorityDefinition
{
	/** @var Definition|string */
	protected $definition;
	protected int $priority;

	/**
	 * PriorityDefinition constructor.
	 * @param Definition|string $definition
	 * @param int $priority
	 */
	public function __construct($definition, int $priority = 0) {
		$this->definition = $definition;
		$this->priority = $priority;
	}

	public function getPriority(): int {
		return $this->priority;
	}

	/**
	 * @return Definition|string
	 */
	public function getDefinition() {
		return $this->definition;
	}
}