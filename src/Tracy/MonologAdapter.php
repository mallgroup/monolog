<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog\Tracy;

use Monolog\Logger as MonologLogger;
use Nette\SmartObject;
use Tracy\Helpers;
use Tracy\Logger;

/**
 * Replaces the default Tracy logger,
 * which allows to preprocess all messages and pass then to Monolog for processing.
 */
class MonologAdapter extends Logger
{

	use SmartObject;

	public const ACCESS = 'access';

	/**
	 * @var array<string,int>
	 */
	private array $priorityMap = [
		self::DEBUG => MonologLogger::DEBUG,
		self::INFO => MonologLogger::INFO,
		self::WARNING => MonologLogger::WARNING,
		self::ERROR => MonologLogger::ERROR,
		self::EXCEPTION => MonologLogger::CRITICAL,
		self::CRITICAL => MonologLogger::CRITICAL,
	];
	private MonologLogger $monolog;
	private BlueScreenRenderer $blueScreenRenderer;
	private string $accessPriority;

	public function __construct(
		MonologLogger $monolog,
		BlueScreenRenderer $blueScreenRenderer,
		string $email = NULL,
		string $accessPriority = self::INFO
	)
	{
		parent::__construct($blueScreenRenderer->directory, $email);
		$this->monolog = $monolog;
		$this->blueScreenRenderer = $blueScreenRenderer;
		$this->accessPriority = $accessPriority;
	}

	public function getExceptionFile(\Throwable $exception, string $level = self::EXCEPTION): string
	{
		return $this->blueScreenRenderer->getExceptionFile($exception);
	}

	/**
	 * @param string|\Throwable $message
	 * @param string $level
	 * @return string|null
	 */
	public function log($message, $level = self::INFO): ?string
	{
		$formattedMessage = self::formatMessage($message);
		$context = [
			'priority' => $level,
			'at' => Helpers::getSource(),
		];

		if ($message instanceof \Throwable) {
			$context['exception'] = $message;
		}

		$exceptionFile = $message instanceof \Throwable ? $this->getExceptionFile($message) : NULL;

		if ($this->email !== NULL && $this->mailer !== NULL && in_array($level, [self::ERROR, self::EXCEPTION, self::CRITICAL], TRUE)) {
			$this->sendEmail(implode(' ', [
				@date('[Y-m-d H-i-s]'),
				$formattedMessage,
				' @ ' . Helpers::getSource(),
				($exceptionFile !== NULL) ? ' @@ ' . basename($exceptionFile) : NULL,
			]));
		}

		if ($level === self::ACCESS) {
			$level = $this->accessPriority;
		}

		$this->monolog->addRecord(
			$this->getLevel($level),
			$formattedMessage,
			$context
		);

		return $exceptionFile;
	}

	protected function getLevel(string $priority): int
	{
		if (isset($this->priorityMap[$priority])) {
			return $this->priorityMap[$priority];
		}

		$levels = MonologLogger::getLevels();
		return $levels[strtoupper($priority)] ?? MonologLogger::INFO;
	}

}
