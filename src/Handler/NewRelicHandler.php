<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog\Handler;

class NewRelicHandler extends \Monolog\Handler\NewRelicHandler
{

	use \Nette\SmartObject;

	/**
	 * {@inheritdoc}
	 */
	protected function write(array $record): void
	{
		if (!$this->isNewRelicEnabled()) {
			return;
		}

		parent::write($record);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isHandling(array $record): bool
	{
		if (!$this->isNewRelicEnabled()) {
			return FALSE;
		}

		return parent::isHandling($record);
	}

}
