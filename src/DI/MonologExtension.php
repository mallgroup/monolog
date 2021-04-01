<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Mall Group (radovan.kepak@mallgroup.com)
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace MG\Monolog\DI;

use MG\Monolog\Handler\FallbackNetteHandler;
use MG\Monolog\Logger as MGLogger;
use MG\Monolog\Processor\PriorityProcessor;
use MG\Monolog\Processor\TracyExceptionProcessor;
use MG\Monolog\Processor\TracyUrlProcessor;
use MG\Monolog\Tracy\BlueScreenRenderer;
use MG\Monolog\Tracy\MonologAdapter;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Config\Helpers;
use Nette\DI\ContainerBuilder;
use Nette\DI\Helpers as DIHelpers;
use Nette\DI\Statement;
use Nette\DI\Definitions;
use Nette\PhpGenerator\ClassType as ClassTypeGenerator;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpLiteral;
use Nette\SmartObject;
use Psr\Log\LoggerAwareInterface;
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

	/** @var array<string,mixed> */
	private array $defaults = [
		'handlers' => [],
		'processors' => [],
		'name' => 'app',
		'hookToTracy' => TRUE,
		'tracyBaseUrl' => NULL,
		'usePriorityProcessor' => TRUE,
		'registerFallback' => FALSE,
		'accessPriority' => ILogger::INFO,
	];

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var array<string,mixed> $config */
		$config = Helpers::merge($this->config, $this->defaults);
		$config['logDir'] = self::resolveLogDir($builder->parameters);
		self::createDirectory($config['logDir']);
		$this->setConfig($config);

		if (!isset($builder->parameters[$this->name]) || (is_array($builder->parameters[$this->name]) && !isset($builder->parameters[$this->name]['name']))) {
			$builder->parameters[$this->name]['name'] = $config['name'];
		}

		if (!isset($builder->parameters['logDir'])) { // BC
			$builder->parameters['logDir'] = $config['logDir'];
		}

		$builder->addDefinition($this->prefix('logger'))
			->setFactory(MGLogger::class, [$config['name']]);

		// Tracy adapter
		$builder->addDefinition($this->prefix('adapter'))
			->setFactory(MonologAdapter::class, [
				'monolog' => $this->prefix('@logger'),
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				'email' => Debugger::$email,
				'accessPriority' => $config['accessPriority'],
			])
			->addTag('logger');

		// The renderer has to be separate, to solve circural service dependencies
		$builder->addDefinition($this->prefix('blueScreenRenderer'))
			->setFactory(BlueScreenRenderer::class, [
				'directory' => $config['logDir'],
			])
			->setAutowired(FALSE)
			->addTag('logger');

		if ($config['hookToTracy'] === TRUE && $builder->hasDefinition('tracy.logger')) {
			// TracyExtension initializes the logger from DIC, if definition is changed
			$builder->removeDefinition($existing = 'tracy.logger');
			$builder->addAlias($existing, $this->prefix('adapter'));
		}

		$this->loadHandlers($config);
		$this->loadProcessors($config);
		$this->setConfig($config);
	}

	/**
	 * @param array<string,mixed> $config
	 */
	protected function loadHandlers(array $config): void
	{
		$builder = $this->getContainerBuilder();
		foreach ($config['handlers'] as $handlerName => $implementation) {
			$builder->addDefinition($this->prefix('handler.' . $handlerName))
				->setFactory($implementation)
				->setAutowired(FALSE)
				->addTag(self::TAG_HANDLER)
				->addTag(self::TAG_PRIORITY, is_numeric($handlerName) ? $handlerName : 0);
		}
	}

	/**
	 * @param array<string,mixed> $config
	 */
	protected function loadProcessors(array $config): void
	{
		$builder = $this->getContainerBuilder();

		if ($config['usePriorityProcessor'] === TRUE) {
			// change channel name to priority if available
			$builder->addDefinition($this->prefix('processor.priorityProcessor'))
				->setFactory(PriorityProcessor::class)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 20);
		}

		$builder->addDefinition($this->prefix('processor.tracyException'))
			->setFactory(TracyExceptionProcessor::class, [
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
			])
			->addTag(self::TAG_PROCESSOR)
			->addTag(self::TAG_PRIORITY, 100);

		if ($config['tracyBaseUrl'] !== NULL) {
			$builder->addDefinition($this->prefix('processor.tracyBaseUrl'))
				->setFactory(TracyUrlProcessor::class, [
					'baseUrl' => $config['tracyBaseUrl'],
					'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				])
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 10);
		}

		foreach ($config['processors'] as $processorName => $implementation) {
			$builder->addDefinition($this->prefix('processors.' . $processorName))
				->setFactory($implementation)
				->setAutowired(FALSE)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, is_numeric($processorName) ? $processorName : 0);
		}
	}

	/**
	 * @param ContainerBuilder $builder
	 * @param string|int $processorName
	 * @param Statement $implementation
	 * @return string
	 */
	protected function loadDefinitions(ContainerBuilder $builder, $processorName, $implementation): string
	{
		if (method_exists($this->compiler, 'loadDefinitionsFromConfig')) {
			$this->compiler->loadDefinitionsFromConfig([
				$serviceName = $this->prefix('processor.' . $processorName) => $implementation,
			]);
		} else {
			Compiler::loadDefinitions($builder, [
				$serviceName = $this->prefix('handler.' . $processorName) => $implementation,
			]);
		}

		return $serviceName;
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$logger = $builder->getDefinition($this->prefix('logger'));

		foreach ($handlers = $this->findByTagSorted(self::TAG_HANDLER) as $serviceName => $meta) {
			$logger->addSetup('pushHandler', ['@' . $serviceName]);
		}

		foreach ($this->findByTagSorted(self::TAG_PROCESSOR) as $serviceName => $meta) {
			$logger->addSetup('pushProcessor', ['@' . $serviceName]);
		}

		if (empty($handlers) && !array_key_exists('registerFallback', $this->config)) {
			$this->config['registerFallback'] = TRUE;
		}

		if (array_key_exists('registerFallback', $this->config) && !empty($this->config['registerFallback'])) {
			$logger->addSetup('pushHandler', [
				new Statement(FallbackNetteHandler::class, [
					'appName' => $this->config['name'],
					'logDir' => $this->config['logDir'],
				]),
			]);
		}

		foreach ($builder->findByType(LoggerAwareInterface::class) as $service) {
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
		uksort($services, static function ($nameA, $nameB) use ($builder) {
			$pa = $builder->getDefinition($nameA)->getTag(self::TAG_PRIORITY) ?: 0;
			$pb = $builder->getDefinition($nameB)->getTag(self::TAG_PRIORITY) ?: 0;
			return $pa > $pb ? 1 : ($pa < $pb ? -1 : 0);
		});

		return $services;
	}

	public function afterCompile(ClassTypeGenerator $class): void
	{
		$closure = new Closure;
		$closure->addBody('?::setLogger($this->getService(?));', [new PhpLiteral(Debugger::class), $this->prefix('adapter')]);

		$initialize = $class->getMethod('initialize');
		if (Debugger::$logDirectory === NULL && array_key_exists('logDir', $this->config)) {
			$initialize->addBody('?::$logDirectory = ?;', [new PhpLiteral(Debugger::class), $this->config['logDir']]);
		}
		$initialize->addBody("// monolog\n($closure)();");
	}

	public static function register(Configurator $configurator): void
	{
		$configurator->onCompile[] = static function ($config, Compiler $compiler) {
			$compiler->addExtension('monolog', new MonologExtension());
		};
	}

	/**
	 * @param array<string,mixed> $parameters
	 * @return string
	 */
	private static function resolveLogDir(array $parameters): string
	{
		if (isset($parameters['logDir'])) {
			return DIHelpers::expand('%logDir%', $parameters);
		}

		return Debugger::$logDirectory ?? DIHelpers::expand('%appDir%/../log', $parameters);
	}

	/**
	 * @param string $logDir
	 */
	private static function createDirectory(string $logDir): void
	{
		if (!@mkdir($logDir, 0777, TRUE) && !is_dir($logDir)) {
			throw new \RuntimeException(sprintf('Log dir %s cannot be created', $logDir));
		}
	}
}
