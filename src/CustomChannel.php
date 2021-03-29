<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog;

use MG\Monolog\Logger as CustomLogger;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger as MonologLogger;
use Nette\SmartObject;

class CustomChannel extends Logger
{

	use SmartObject;

	private Logger $parentLogger;

	public function __construct(string $name, CustomLogger $parentLogger)
	{
		parent::__construct($name, [], []);
		$this->parentLogger = $parentLogger;
	}

	public function pushHandler(HandlerInterface $handler): MonologLogger
	{
		return $this->parentLogger->pushHandler($handler);
	}

	public function popHandler(): HandlerInterface
	{
		return $this->parentLogger->popHandler();
	}

	public function getHandlers(): array
	{
		return $this->parentLogger->getHandlers();
	}

	public function pushProcessor(callable $callback): MonologLogger
	{
		return $this->parentLogger->pushProcessor($callback);
	}

	public function popProcessor(): callable
	{
		return $this->parentLogger->popProcessor();
	}

	public function getProcessors(): array
	{
		return $this->parentLogger->getProcessors();
	}

	public function addRecord(int $level, string $message, array $context = []): bool
	{
		return $this->parentLogger->addRecord($level, $message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the DEBUG level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function addDebug(string $message, array $context = []): void
	{
		$this->parentLogger->debug($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the INFO level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function addInfo($message, array $context = []): void
	{
		$this->parentLogger->info($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the NOTICE level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function addNotice($message, array $context = []): void
	{
		$this->parentLogger->notice($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the WARNING level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function addWarning($message, array $context = []): void
	{
		$this->parentLogger->warning($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the ERROR level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function addError($message, array $context = []): void
	{
		$this->parentLogger->error($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the CRITICAL level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function addCritical($message, array $context = []): void
	{
		$this->parentLogger->critical($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the ALERT level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function addAlert($message, array $context = []): void
	{
		$this->parentLogger->alert($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the EMERGENCY level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function addEmergency($message, array $context = []): void
	{
		$this->parentLogger->emergency($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function isHandling(int $level): bool
	{
		return $this->parentLogger->isHandling($level);
	}

	/**
	 * {@inheritdoc}
	 */
	public function log($level, $message, array $context = []): void
	{
		$this->parentLogger->log($level, $message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function debug($message, array $context = []): void
	{
		$this->parentLogger->debug($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function info($message, array $context = []): void
	{
		$this->parentLogger->info($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function notice($message, array $context = []): void
	{
		$this->parentLogger->notice($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the WARNING level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function warn($message, array $context = []): void
	{
		$this->parentLogger->warning($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function warning($message, array $context = []): void
	{
		$this->parentLogger->warning($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the ERROR level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function err($message, array $context = []): void
	{
		$this->parentLogger->error($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function error($message, array $context = []): void
	{
		$this->parentLogger->error($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the CRITICAL level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function crit($message, array $context = []): void
	{
		$this->parentLogger->critical($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function critical($message, array $context = []): void
	{
		$this->parentLogger->critical($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function alert($message, array $context = []): void
	{
		$this->parentLogger->alert($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the EMERGENCY level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public function emerg($message, array $context = []): void
	{
		$this->parentLogger->emergency($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function emergency($message, array $context = []): void
	{
		$this->parentLogger->emergency($message, array_merge(['channel' => $this->name], $context));
	}

}
