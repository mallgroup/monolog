<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog\Processor;

use MG\Monolog\Tracy\BlueScreenRenderer;

class TracyUrlProcessor
{

	use \Nette\SmartObject;

	/**
	 * @var string
	 */
	private $baseUrl;

	/**
	 * @var \MG\Monolog\Tracy\BlueScreenRenderer
	 */
	private $blueScreenRenderer;

	public function __construct($baseUrl, BlueScreenRenderer $blueScreenRenderer)
	{
		$this->baseUrl = rtrim($baseUrl, '/');
		$this->blueScreenRenderer = $blueScreenRenderer;
	}

	public function __invoke(array $record)
	{
		if ($this->isHandling($record)) {
			$exceptionFile = $this->blueScreenRenderer->getExceptionFile($record['context']['exception']);
			$record['context']['tracyUrl'] = sprintf('%s/%s', $this->baseUrl, basename($exceptionFile));
		}

		return $record;
	}

	public function isHandling(array $record): bool
	{
		return isset($record['context']['exception'])
			&& ($record['context']['exception'] instanceof \Throwable || $record['context']['exception'] instanceof \Exception);
	}

}
