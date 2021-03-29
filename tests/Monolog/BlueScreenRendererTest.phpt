<?php

/**
 * Test: MG\Monolog\MonologAdapter.
 *
 * @testCase
 */

namespace Tests\Monolog;

use MG\Monolog\Tracy\BlueScreenRenderer;
use Tester\Assert;
use Tracy\BlueScreen;

require_once __DIR__ . '/../bootstrap.php';

class BlueScreenRendererTest extends \Tester\TestCase
{

	public function testLogginIsNotSupported(): void
	{
		$renderer = new BlueScreenRenderer(TEMP_DIR, new BlueScreen());

		Assert::exception(function () use ($renderer) {
			$renderer->log('message');
		}, \MG\Monolog\Exception\NotSupportedException::class, 'This class is only for rendering exceptions');
	}

}

(new BlueScreenRendererTest())->run();
