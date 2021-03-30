<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog\Processor;

use MG\Monolog\Tracy\BlueScreenRenderer;
use Nette\SmartObject;

class TracyUrlProcessor
{

	use SmartObject;

	private string $baseUrl;
	private BlueScreenRenderer $blueScreenRenderer;

	public function __construct(string $baseUrl, BlueScreenRenderer $blueScreenRenderer)
	{
		$this->baseUrl = rtrim($baseUrl, '/');
		$this->blueScreenRenderer = $blueScreenRenderer;
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array<string,mixed>
	 */
	public function __invoke(array $record): array
	{
		if ($this->isHandling($record)) {
			$exceptionFile = $this->blueScreenRenderer->getExceptionFile($record['context']['exception']);
			$record['context']['tracyUrl'] = sprintf('%s/%s', $this->baseUrl, basename($exceptionFile));
		}

		return $record;
	}

	/**
	 * @param array<string,mixed> $record
	 * @return bool
	 */
	public function isHandling(array $record): bool
	{
		return isset($record['context']['exception']) && ($record['context']['exception'] instanceof \Throwable);
	}

}
