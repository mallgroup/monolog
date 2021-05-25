<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Mallgroup\Monolog\DI;

use Mallgroup\Monolog\Handler\FallbackNetteHandler;
use Mallgroup\Monolog\Logger;
use Mallgroup\Monolog\Processor\PriorityProcessor;
use Mallgroup\Monolog\Processor\TracyExceptionProcessor;
use Mallgroup\Monolog\Processor\TracyUrlProcessor;
use Mallgroup\Monolog\Tracy\BlueScreenRenderer;
use Mallgroup\Monolog\Tracy\MonologAdapter;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\SmartObject;
use Nette\Utils\Strings;
use Psr\Log\LoggerAwareInterface;
use RuntimeException;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Integrates the Monolog seamlessly into your Nette Framework application.
 */
class MonologExtension extends CompilerExtension
{
	use SmartObject;

	public const TAG_HANDLER = 'monolog.handler';
	public const TAG_PROCESSOR = 'monolog.processor';
	public const TAG_PRIORITY = 'monolog.priority';

	public static function register(Configurator $configurator): void
	{
		$configurator->onCompile[] = static function ($config, Compiler $compiler) {
			$compiler->addExtension('monolog', new MonologExtension());
		};
	}

	public function getConfigSchema(): Schema
	{
		$builder = $this->getContainerBuilder();
		return Expect::structure([
			'handlers' => Expect::arrayOf('string|array'),
			'processors' => Expect::arrayOf('string|array'),
			'name' => Expect::string('app'),
			'logDir' => Expect::string($builder->parameters['tempDir'] . '/log'),
			'tracyDefinition' => Expect::string('tracy.logger'),
			'tracyHook' => Expect::bool(true),
			'tracyBaseUrl' => Expect::string(),
			'usePriorityProcessor' => Expect::bool(true),
			'registerFallback' => Expect::bool(false),
			'accessPriority' => Expect::string(ILogger::INFO),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$this->verifyLogDirectory($this->config->logDir);

		$builder
			->addDefinition($this->prefix('logger'))
			->setFactory(Logger::class, [$this->config->name]);

		# Hook tracy if required
		if ($builder->hasDefinition($this->config->tracyDefinition)) {
			$this->hookTracy();
		}

		# Load other stuff
		$this->loadHandlers();
		$this->loadProcessors();
	}

	protected function loadHandlers(): void
	{
		$builder = $this->getContainerBuilder();
		foreach($this->config->handlers as $name => $implementation) {
			$service = $this->getDefinition(
				$implementation,
				$this->prefix('handler.' . $name)
			);
			$builder
				->getDefinition($service)
				->addTag(self::TAG_HANDLER)
				->addTag(self::TAG_PRIORITY, is_numeric($name) ? $name : 0);
		}
	}

	protected function loadProcessors(): void
	{
		$builder = $this->getContainerBuilder();
		if ($this->config->usePriorityProcessor === true) {
			$builder
				->addDefinition($this->prefix('processor.priorityProcessor'))
				->setFactory(PriorityProcessor::class)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 20);
		}

		foreach($this->config->processors as $name => $implementation) {
			$service = $this->getDefinition(
				$implementation,
				$this->prefix('processors.' . $name)
			);
			$builder
				->getDefinition($service)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, is_numeric($name) ? $name : 0);
		}
	}

	/**
	 * @param string|mixed[]|Statement $definition
	 * @param string $name
	 * @return string
	 */
	protected function getDefinition($definition, string $name): string
	{
		// String definition
		if (is_string($definition)) {

			// @alias
			if (Strings::startsWith($definition, '@')) {
				return $definition;
			}

			// Inline string definition
			$this
				->getContainerBuilder()
				->addDefinition($name, (new ServiceDefinition())->setType($definition))
				->setAutowired(false);
			return $name;
		}

		// Add service and set it autowired (default is false)
		if (is_array($definition)) {
			$definition['autowired'] ??= false;
		}
		$this->compiler->loadDefinitionsFromConfig([
			$name => $definition
		]);

		return $name;
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		/** @var ServiceDefinition $logger */
		$logger = $builder->getDefinition($this->prefix('logger'));
		foreach ($handlers = $this->findByTagSorted(self::TAG_HANDLER) as $serviceName => $meta) {
			$logger->addSetup('pushHandler', ['@' . $serviceName]);
		}
		foreach ($this->findByTagSorted(self::TAG_PROCESSOR) as $serviceName => $meta) {
			$logger->addSetup('pushProcessor', ['@' . $serviceName]);
		}

		// Register fallback, if no handlers is set or registerFallback is true
		if (!$handlers || $this->config->registerFallback) {
			$builder
				->addDefinition($this->prefix('fallback'))
				->setFactory(FallbackNetteHandler::class, [
					'appName' => $this->config->name,
					'logDir' => $this->config->logDir
				])
				->setAutowired(false)
				->addTag(self::TAG_HANDLER)
				->addTag(self::TAG_PRIORITY, 0);

			$logger->addSetup('pushHandler', ['@' . $this->prefix('fallback')]);
		}

		// Decorator for LoggerAwareInterface
		foreach ($builder->findByType(LoggerAwareInterface::class) as $service) {
			/** @var ServiceDefinition $service */
			$service->addSetup('setLogger', ['@' . $this->prefix('logger')]);
		}
	}

	/**
	 * @param string $tag
	 * @return array<string,bool>
	 */
	protected function findByTagSorted(string $tag): array
	{
		$builder = $this->getContainerBuilder();
		$services = $builder->findByTag($tag);

		uksort(
			$services,
			static fn($a, $b) => ($builder->getDefinition($a)->getTag(self::TAG_PRIORITY) ?: 0) <=> ($builder->getDefinition($b)->getTag(self::TAG_PRIORITY) ?: 0)
		);

		return $services;
	}

	public function afterCompile(ClassType $class): void
	{
		if (!$this->config->tracyHook
			|| !class_exists(Debugger::class)
			|| !$this->getContainerBuilder()->hasDefinition($this->config->tracyDefinition)
		) {
			return;
		}

		$closure = new Closure;
		$closure->addBody('?::setLogger($this->getService(?));', [new PhpLiteral(Debugger::class), $this->prefix('tracy')]);

		$initialize = $class->getMethod('initialize');

		if (Debugger::$logDirectory === null) {
			$initialize->addBody('?::$logDirectory = ?;', [new PhpLiteral(Debugger::class), $this->config->logDir]);
		}

		$initialize->addBody("// monolog\n($closure)();");
	}

	/**
	 * @param string $logDir
	 * @throws RuntimeException
	 */
	private function verifyLogDirectory(string $logDir): void
	{
		if (!@mkdir($logDir, 0777, true) && !is_dir($logDir)) {
			throw new RuntimeException(sprintf('Log dir %s cannot be created', $logDir));
		}
	}

	private function hookTracy(): void
	{
		$builder = $this->getContainerBuilder();
		$builder
			->addDefinition($this->prefix('tracy'))
			->setFactory(MonologAdapter::class, [
				'monolog' => $this->prefix('@logger'),
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				'email' => Debugger::$email,
				'accessPriority' => $this->config->accessPriority,
			])
			->addTag('logger')
			->setAutowired(false);

		$builder
			->addDefinition($this->prefix('blueScreenRenderer'))
			->setFactory(BlueScreenRenderer::class, [
				'directory' => $this->config->logDir
			])
			->setAutowired(false)
			->addTag('logger');

		$builder
			->addDefinition($this->prefix('processor.tracyException'))
			->setFactory(TracyExceptionProcessor::class, [
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
			])
			->addTag(self::TAG_PROCESSOR)
			->addTag(self::TAG_PRIORITY, 100);

		if ($this->config->tracyBaseUrl) {
			$builder
				->addDefinition($this->prefix('processor.tracyBaseUrl'))
				->setFactory(TracyUrlProcessor::class, [
					'baseUrl' => $this->config->tracyBaseUrl,
					'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				])
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 10);
		}

		if ($this->config->tracyHook === true) {
			$builder->removeDefinition($this->config->tracyDefinition);
			$builder->addAlias($this->config->tracyDefinition, $this->prefix('tracy'));
			$builder->getDefinition($this->prefix('tracy'))->setAutowired(true);
		}
	}
}
