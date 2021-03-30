<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog\Handler;

use Monolog\Handler\MissingExtensionException;
use Nette\SmartObject;

class NewRelicHandler extends \Monolog\Handler\NewRelicHandler
{

	use SmartObject;

	/**
	 * @param array<string,mixed> $record
	 * @throws MissingExtensionException
	 */
	protected function write(array $record): void
	{
		if (!$this->isNewRelicEnabled()) {
			return;
		}

		parent::write($record);
	}

	/**
	 * @param array<string,mixed> $record
	 * @return bool
	 */
	public function isHandling(array $record): bool
	{
		if (!$this->isNewRelicEnabled()) {
			return FALSE;
		}

		return parent::isHandling($record);
	}

}
