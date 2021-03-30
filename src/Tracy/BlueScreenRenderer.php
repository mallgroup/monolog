<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog\Tracy;

use MG\Monolog\Exception\NotSupportedException;
use Nette\SmartObject;
use Throwable;
use Tracy\BlueScreen;
use Tracy\Logger;

class BlueScreenRenderer extends Logger
{

	use SmartObject;

	public function __construct(string $directory, BlueScreen $blueScreen)
	{
		parent::__construct($directory, NULL, $blueScreen);
	}

	/**
	 * @param \Throwable $exception
	 * @param string $file
	 * @return string logged error filename
	 */
	public function renderToFile(Throwable $exception, string $file): string
	{
		return $this->logException($exception, $file);
	}

	/**
	 * @internal
	 * @deprecated
	 */
	public function log($message, $level = self::INFO): ?string
	{
		throw new NotSupportedException('This class is only for rendering exceptions');
	}

	/**
	 * @internal
	 * @deprecated
	 */
	public function defaultMailer($message, string $email): void
	{
	}

}
