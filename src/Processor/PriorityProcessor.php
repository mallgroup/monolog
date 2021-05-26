<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Mallgroup\Monolog\Processor;

use Monolog\Logger as MonologLogger;
use Nette\SmartObject;

/**
 * Helps you change the channel name of the record,
 * when you wanna have multiple log files coming out of your application.
 */
class PriorityProcessor
{

	use SmartObject;

	/**
	 * @param array<string,mixed> $record
	 * @return array<string,mixed>
	 */
	public function __invoke(array $record): array
	{
		if (isset($record['context']['channel'])) {
			$record['channel'] = $record['context']['channel'];
			unset($record['context']['channel']);

		} elseif (isset($record['context']['priority'])) {
			$rename = strtoupper($record['context']['priority']);
			if (!array_key_exists($rename, MonologLogger::getLevels())) {
				$record['channel'] = strtolower($rename);
			}
			unset($record['context']['priority']);
		}
		return $record;
	}

}
