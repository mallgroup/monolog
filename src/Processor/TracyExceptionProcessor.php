<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Mallgroup\Monolog\Processor;

use Mallgroup\Monolog\Tracy\BlueScreenRenderer;
use Nette\SmartObject;

class TracyExceptionProcessor
{

	use SmartObject;

	private BlueScreenRenderer $blueScreenRenderer;

	public function __construct(BlueScreenRenderer $blueScreenRenderer)
	{
		$this->blueScreenRenderer = $blueScreenRenderer;
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array<string,mixed>
	 */
	public function __invoke(array $record): array
	{
		if (!$this->isHandling($record)) {
			return $record;
		}

		$exception = $record['context']['exception'];
		$filename = $this->blueScreenRenderer->getExceptionFile($exception);
		$record['context']['tracy_filename'] = basename($filename);

		if (!file_exists($filename)) {
			$this->blueScreenRenderer->renderToFile($exception, $filename);
			$record['context']['tracy_created'] = TRUE;
		}

		return $record;
	}

	/**
	 * @param array<string,mixed> $record
	 * @return bool
	 */
	public function isHandling(array $record): bool
	{
		return !isset($record['context']['tracy'])
			&& !isset($record['context']['tracy_filename'])
			&& isset($record['context']['exception'])
			&& ($record['context']['exception'] instanceof \Throwable);
	}

}
