<?php

/**
 * Test: MG\Monolog\MonologAdapter.
 *
 * @testCase
 */

namespace Tests\Monolog;

use DateTimeInterface;
use MG\Monolog\Tracy\BlueScreenRenderer;
use MG\Monolog\Tracy\MonologAdapter;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tester\Assert;
use Tracy\BlueScreen;

require_once __DIR__ . '/../bootstrap.php';

class MonologAdapterTest extends \Tester\TestCase
{
	protected MonologAdapter $adapter;
	protected MonologLogger $monolog;
	protected TestHandler $testHandler;

	protected function setUp(): void
	{
		$this->testHandler = new TestHandler();
		$this->monolog = new MonologLogger('mall', [$this->testHandler]);
		$blueScreenRenderer = new BlueScreenRenderer(TEMP_DIR, new BlueScreen());
		$this->adapter = new MonologAdapter($this->monolog, $blueScreenRenderer);
	}

	public function dataLogStandard(): array
	{
		return [
			['test message 1', 'debug'],
			['test message 2', 'info'],
			['test message 3', 'notice'],
			['test message 4', 'warning'],
			['test message 5', 'error'],
			['test message 6', 'critical'],
			['test message 7', 'alert'],
			['test message 8', 'emergency'],
		];
	}

	/**
	 * @dataProvider dataLogStandard
	 */
	public function testLogStandard(string $message, string $priority): void
	{
		Assert::count(0, $this->testHandler->getRecords());
		$this->adapter->log($message, $priority);
		Assert::count(1, $this->testHandler->getRecords());

		[$record] = $this->testHandler->getRecords();

		Assert::same('mall', $record['channel']);
		Assert::same($message, $record['message']);
		Assert::same(strtoupper($priority), $record['level_name']);
		Assert::same($priority, $record['context']['priority']);
		Assert::type(DateTimeInterface::class, $record['datetime']);
		Assert::match('CLI%a%: %a%', $record['context']['at']);
		Assert::contains('MonologAdapterTest.phpt', $record['context']['at']);
	}

	public function testLogWithCustomPriority(): void
	{
		$this->adapter->log('test message', 'nemam');
		Assert::count(1, $this->testHandler->getRecords());

		[$record] = $this->testHandler->getRecords();
		Assert::same('mall', $record['channel']);
		Assert::same('test message', $record['message']);
		Assert::same('INFO', $record['level_name']);
		Assert::same('nemam', $record['context']['priority']);
		Assert::match('CLI%a%: %a%', $record['context']['at']);
		Assert::contains('MonologAdapterTest.phpt', $record['context']['at']);
	}

	public function testLogWithAccessPriority(): void
	{
		$this->adapter->log('test access message', MonologAdapter::ACCESS);
		Assert::count(1, $this->testHandler->getRecords());

		[$record] = $this->testHandler->getRecords();
		Assert::same('mall', $record['channel']);
		Assert::same('test access message', $record['message']);
		Assert::same('INFO', $record['level_name']);
		Assert::same(MonologAdapter::ACCESS, $record['context']['priority']);
		Assert::match('CLI%a%: %a%', $record['context']['at']);
		Assert::contains('MonologAdapterTest.phpt', $record['context']['at']);
	}

}

(new MonologAdapterTest())->run();
