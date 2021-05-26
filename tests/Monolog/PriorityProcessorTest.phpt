<?php

/**
 * Test: Mallgroup\Monolog\PriorityProcessor.
 *
 * @testCase
 */

namespace Tests\Monolog;

use Mallgroup\Monolog\Processor\PriorityProcessor;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class PriorityProcessorTest extends \Tester\TestCase
{

	public function dataFunctional(): array
	{
		return [
			[
				['channel' => 'mall', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'debug']],
			],
			[
				['channel' => 'mall', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'info']],
			],
			[
				['channel' => 'mall', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'notice']],
			],
			[
				['channel' => 'mall', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'warning']],
			],
			[
				['channel' => 'mall', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'error']],
			],
			[
				['channel' => 'mall', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'critical']],
			],
			[
				['channel' => 'mall', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'alert']],
			],
			[
				['channel' => 'mall', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'emergency']],
			],

			// when bluescreen is rendered Tracy
			[
				['channel' => 'exception', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'exception']],
			],

			// custom priority
			[
				['channel' => 'nemam', 'context' => []],
				['channel' => 'mall', 'context' => ['priority' => 'nemam']],
			],

			// custom channel provided in $context parameter when adding record
			[
				['channel' => 'emails', 'context' => []],
				['channel' => 'mall', 'context' => ['channel' => 'emails']],
			],
			[
				['channel' => 'smses', 'context' => []],
				['channel' => 'mall', 'context' => ['channel' => 'smses']],
			],
		];
	}

	/**
	 * @dataProvider dataFunctional
	 */
	public function testFunctional($expectedRecord, $providedRecord): void
	{
		Assert::same($expectedRecord, call_user_func(new PriorityProcessor(), $providedRecord));
	}

}

(new PriorityProcessorTest())->run();
