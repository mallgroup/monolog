<?php

/**
 * Test: Mallgroup\Monolog\Processor\TracyUrlProcessor.
 *
 * @testCase
 */

namespace Tests\Monolog;

use Mallgroup\Monolog\Processor\TracyUrlProcessor;
use Mallgroup\Monolog\Tracy\BlueScreenRenderer;
use Tester\Assert;
use Tracy\BlueScreen;

require_once __DIR__ . '/../bootstrap.php';

class TracyUrlProcessorTest extends \Tester\TestCase
{
	private BlueScreenRenderer $blueScreenRenderer;
	private TracyUrlProcessor $processor;

	protected function setUp(): void
	{
		$this->blueScreenRenderer = new BlueScreenRenderer(TEMP_DIR, new BlueScreen());
		$this->processor = new TracyUrlProcessor('https://exceptions.kdyby.org', $this->blueScreenRenderer);
	}

	public function testProcessWithException(): void
	{
		$exception = new \RuntimeException(__FUNCTION__);
		$exceptionFile = basename($this->blueScreenRenderer->getExceptionFile($exception));

		$record = [
			'message' => 'Some error',
			'context' => [
				'exception' => $exception,
			],
		];
		$processed = call_user_func($this->processor, $record);
		Assert::same('https://exceptions.kdyby.org/' . $exceptionFile, $processed['context']['tracyUrl']);
	}

	public function testIgnoreProcessWithoutException(): void
	{
		$record = [
			'message' => 'Some error',
			'context' => [
				'tracy' => 'exception--2016-01-17--17-54--72aee7b518.html',
			],
		];
		$processed = call_user_func($this->processor, $record);
		Assert::false(isset($processed['context']['tracyUrl']));
	}

}

(new TracyUrlProcessorTest())->run();
