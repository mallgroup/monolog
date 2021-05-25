<?php

/**
 * Test: Mallgroup\Monolog\Extension.
 *
 * @testCase
 */

namespace Tests\Monolog;

use Mallgroup\Monolog\CustomChannel;
use Mallgroup\Monolog\DI\MonologExtension;
use Mallgroup\Monolog\Logger as MonologLogger;
use Mallgroup\Monolog\Processor\PriorityProcessor;
use Mallgroup\Monolog\Processor\TracyExceptionProcessor;
use Mallgroup\Monolog\Processor\TracyUrlProcessor;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\NewRelicHandler;
use Monolog\Processor\GitProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
use Nette\Configurator;
use Tester\Assert;
use Tracy\Debugger;

require_once __DIR__ . '/../bootstrap.php';

class ExtensionTest extends \Tester\TestCase
{

	/**
	 * @param string|null $configName
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer($configName = NULL)
	{
		$config = new Configurator();
		$config->setTempDirectory(TEMP_DIR);
		MonologExtension::register($config);
		$config->addConfig(__DIR__ . '/../nette-reset.neon');

		if ($configName !== NULL) {
			$config->addConfig(__DIR__ . '/config/' . $configName . '.neon');
		}

		return $config->createContainer();
	}

	public function testServices(): void
	{
		$dic = $this->createContainer();
		Assert::true($dic->getService('monolog.logger') instanceof MonologLogger);
	}

	public function testFunctional(): void
	{
		foreach (array_merge(glob(TEMP_DIR . '/*.log'), glob(TEMP_DIR . '/*.html')) as $logFile) {
			unlink($logFile);
		}

		Debugger::$logDirectory = TEMP_DIR;

		$dic = $this->createContainer('default');
		/** @var \Mallgroup\Monolog\Logger $logger */
		$logger = $dic->getByType(MonologLogger::class);

		Debugger::log('tracy message 1');
		Debugger::log('tracy message 2', 'error');

		Debugger::log(new \Exception('tracy exception message 1'), 'error');
		Debugger::log(new \Exception('tracy exception message 2'));

		$logger->addInfo('logger message 1');
		$logger->addInfo('logger message 2', ['channel' => 'custom']);

		$logger->addError('logger message 3');
		$logger->addError('logger message 4', ['channel' => 'custom']);

		$logger->addWarning('exception message 1', ['exception' => new \Exception('exception message 1')]);

		$logger->addDebug('logger message 5');
		$logger->addDebug('logger message 6', ['channel' => 'custom']);

		$logger->addNotice('logger message 7');
		$logger->addNotice('logger message 8', ['channel' => 'custom']);

		$logger->addCritical('logger message 9');
		$logger->addCritical('logger message 10', ['channel' => 'custom']);

		$logger->addAlert('logger message 11');
		$logger->addAlert('logger message 12', ['channel' => 'custom']);

		$logger->addEmergency('logger message 13');
		$logger->addEmergency('logger message 14', ['channel' => 'custom']);

		$logger->warn('exception message 2', ['exception' => new \Exception('exception message 2')]);
		$logger->warn('logger message 16', ['channel' => 'custom']);

		$logger->err('logger message 17');
		$logger->err('logger message 18', ['channel' => 'custom']);

		$logger->crit('logger message 19');
		$logger->crit('logger message 20', ['channel' => 'custom']);

		$logger->emerg('logger message 21');
		$logger->emerg('logger message 22', ['channel' => 'custom']);
		Assert::match(
			'[%a%] tracy message 1 {"at":"%a%"} []' . "\n" .
			'[%a%] Exception: tracy exception message 2 in %a%:%d% {"at":"%a%","exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} []' . "\n" .
			'[%a%] logger message 1 [] []',
			file_get_contents(TEMP_DIR . '/log/info.log')
		);
		Assert::match(
			'[%a%] exception message 1 {"exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} []' . "\n" .
			'[%a%] exception message 2 {"exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} []',
			file_get_contents(TEMP_DIR . '/log/warning.log')
		);

		Assert::match(
			'[%a%] tracy message 2 {"at":"%a%"} []' . "\n" .
			'[%a%] Exception: tracy exception message 1 in %a%:%d% {"at":"%a%","exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} []' . "\n" .
			'[%a%] logger message 3 [] []' . "\n" .
			'[%a%] logger message 17 [] []',
			file_get_contents(TEMP_DIR . '/log/error.log')
		);

		Assert::match(
			'[%a%] INFO: logger message 2 [] []' . "\n" .
			'[%a%] ERROR: logger message 4 [] []' . "\n" .
			'[%a%] DEBUG: logger message 6 [] []' . "\n" .
			'[%a%] NOTICE: logger message 8 [] []' . "\n" .
			'[%a%] CRITICAL: logger message 10 [] []' . "\n" .
			'[%a%] ALERT: logger message 12 [] []' . "\n" .
			'[%a%] EMERGENCY: logger message 14 [] []' . "\n" .
			'[%a%] WARNING: logger message 16 [] []' . "\n" .
			'[%a%] ERROR: logger message 18 [] []' . "\n" .
			'[%a%] CRITICAL: logger message 20 [] []' . "\n" .
			'[%a%] EMERGENCY: logger message 22 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/log/custom.log')
		);

		Assert::match(
			'[%a%] logger message 5 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/log/debug.log')
		);

		Assert::match(
			'[%a%] logger message 7 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/log/notice.log')
		);

		Assert::match(
			'[%a%] logger message 9 [] []' . "\n" .
			'[%a%] logger message 19 [] []',
			file_get_contents(TEMP_DIR . '/log/critical.log')
		);

		Assert::match(
			'[%a%] logger message 11 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/log/alert.log')
		);

		Assert::match(
			'[%a%] logger message 13 [] []' . "\n" .
			'[%a%] logger message 21 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/log/emergency.log')
		);

		Assert::count(4, glob(TEMP_DIR . '/log/exception-*.html'));

		// TEST FOR CUSTOM CHANNEL

		$channel = $logger->channel('test');
		Assert::type(CustomChannel::class, $channel);
		Assert::match('test', $channel->getName());

		$channel->addInfo('custom channel message 1');
		$channel->addError('custom channel message 2');
		$channel->addWarning('custom channel message 3');
		$channel->addDebug('custom channel message 4');
		$channel->addNotice('custom channel message 5');
		$channel->addCritical('custom channel message 6');
		$channel->addAlert('custom channel message 7');
		$channel->addEmergency('custom channel message 8');

		$channel->debug('custom channel message 9');
		$channel->info('custom channel message 10');
		$channel->notice('custom channel message 11');
		$channel->warn('custom channel message 12');
		$channel->warning('custom channel message 13');
		$channel->err('custom channel message 14');
		$channel->error('custom channel message 15');
		$channel->crit('custom channel message 16');
		$channel->critical('custom channel message 17');
		$channel->alert('custom channel message 18');
		$channel->emerg('custom channel message 19');
		$channel->emergency('custom channel message 20');

		Assert::match(
			'[%a%] INFO: custom channel message 1 [] []' . "\n" .
			'[%a%] ERROR: custom channel message 2 [] []' . "\n" .
			'[%a%] WARNING: custom channel message 3 [] []' . "\n" .
			'[%a%] DEBUG: custom channel message 4 [] []' . "\n" .
			'[%a%] NOTICE: custom channel message 5 [] []' . "\n" .
			'[%a%] CRITICAL: custom channel message 6 [] []' . "\n" .
			'[%a%] ALERT: custom channel message 7 [] []' . "\n" .
			'[%a%] EMERGENCY: custom channel message 8 [] []' . "\n" .
			'[%a%] DEBUG: custom channel message 9 [] []' . "\n" .
			'[%a%] INFO: custom channel message 10 [] []' . "\n" .
			'[%a%] NOTICE: custom channel message 11 [] []' . "\n" .
			'[%a%] WARNING: custom channel message 12 [] []' . "\n" .
			'[%a%] WARNING: custom channel message 13 [] []' . "\n" .
			'[%a%] ERROR: custom channel message 14 [] []' . "\n" .
			'[%a%] ERROR: custom channel message 15 [] []' . "\n" .
			'[%a%] CRITICAL: custom channel message 16 [] []' . "\n" .
			'[%a%] CRITICAL: custom channel message 17 [] []' . "\n" .
			'[%a%] ALERT: custom channel message 18 [] []' . "\n" .
			'[%a%] EMERGENCY: custom channel message 19 [] []' . "\n" .
			'[%a%] EMERGENCY: custom channel message 20 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/log/test.log')
		);
	}

	public function testHandlersSorting(): void
	{
		$dic = $this->createContainer('handlers');
		$logger = $dic->getByType(MonologLogger::class);
		$handlers = $logger->getHandlers();
		Assert::count(3, $handlers);
		Assert::type(NewRelicHandler::class, array_shift($handlers));
		Assert::type(ChromePHPHandler::class, array_shift($handlers));
		Assert::type(BrowserConsoleHandler::class, array_shift($handlers));
	}

	public function testProcessorsSorting(): void
	{
		$dic = $this->createContainer('processors');
		$logger = $dic->getByType(MonologLogger::class);
		$processors = $logger->getProcessors();
		Assert::count(6, $processors);
		Assert::type(TracyExceptionProcessor::class, array_shift($processors));
		Assert::type(PriorityProcessor::class, array_shift($processors));
		Assert::type(TracyUrlProcessor::class, array_shift($processors));
		Assert::type(WebProcessor::class, array_shift($processors));
		Assert::type(ProcessIdProcessor::class, array_shift($processors));
		Assert::type(GitProcessor::class, array_shift($processors));
	}

}

(new ExtensionTest())->run();
