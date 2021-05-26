<?php

/**
 * Test: Mallgroup\Monolog\Processor\TracyExceptionProcessor.
 *
 * @testCase
 */

namespace Tests\Monolog;

use Mallgroup\Monolog\Processor\TracyExceptionProcessor;
use Mallgroup\Monolog\Tracy\BlueScreenRenderer;
use Tester\Assert;
use Tracy\BlueScreen;

require_once __DIR__ . '/../bootstrap.php';

class TracyExceptionProcessorTest extends \Tester\TestCase
{
	private TracyExceptionProcessor $processor;

	protected function setUp(): void
	{
		$this->processor = new TracyExceptionProcessor(new BlueScreenRenderer(TEMP_DIR, new BlueScreen()));
	}

	public function testIgnoreAlreadyProcessed(): void
	{
		$exception = new \RuntimeException('ignore me please');
		$record = [
			'message' => 'Some error',
			'context' => [
				'tracy' => 'exception--2016-01-17--17-54--72aee7b518.html',
				'exception' => $exception,
			],
		];
		$updatedRecord = call_user_func($this->processor, $record);
		Assert::same($record, $updatedRecord);
	}

	public function testLogBlueScreenFromContext(): void
	{
		$exception = new \RuntimeException('message');
		$record = [
			'message' => 'Some error',
			'context' => [
				'exception' => $exception,
			],
		];
		$updatedRecord = call_user_func($this->processor, $record);
		Assert::true($updatedRecord['context']['tracy_created']);
		Assert::match('exception-%a%.html', $updatedRecord['context']['tracy_filename']);
		Assert::true(file_exists(TEMP_DIR . '/' . $updatedRecord['context']['tracy_filename']));
		Assert::same($exception, $updatedRecord['context']['exception']);
	}
}

(new TracyExceptionProcessorTest())->run();
