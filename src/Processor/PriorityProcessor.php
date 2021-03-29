<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog\Processor;

use Monolog\Logger as MonologLogger;

/**
 * Helps you change the channel name of the record,
 * when you wanna have multiple log files coming out of your application.
 */
class PriorityProcessor
{

	use \Nette\SmartObject;

	public function __invoke($record)
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
