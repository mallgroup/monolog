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
use Monolog\ErrorHandler;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
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
	private const
		EXCEPTION_HANDLER = 'exception',
		ERROR_HANDLER = 'error',
		FATAL_HANDLER = 'fatal';

	/** @var PriorityDefinition[] */
	private array $handlers = [];

	/** @var PriorityDefinition[] */
	private array $processors = [];

	public static function register(Configurator $configurator): void
	{
		$configurator->onCompile[] = static function ($config, Compiler $compiler) {
			$compiler->addExtension('monolog', new MonologExtension);
		};
	}

	public function getConfigSchema(): Schema
	{
		$builder = $this->getContainerBuilder();
		$definition = Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class));
		return Expect::structure([
			'handlers' => Expect::arrayOf($definition),
			'processors' => Expect::arrayOf($definition),
			'name' => Expect::string('app'),
			'logDir' => Expect::string($builder->parameters['tempDir'] . '/log'),
			'tracyDefinition' => Expect::string('tracy.logger'),
			'tracyHook' => Expect::bool(true),
			'tracyBaseUrl' => Expect::string(),
			'tracyRenderException' => Expect::bool(true),
			'usePriorityProcessor' => Expect::bool(true),
			'fallback' => Expect::structure([
				'register' => Expect::bool(false),
				'defaultFormat' => Expect::string('[%datetime%] %message% %context% %extra%'),
				'priorityFormat' => Expect::string('[%datetime%] %level_name%: %message% %context% %extra%'),
			]),
			'registerFallback' => Expect::bool(false)->deprecated(),
			'accessPriority' => Expect::string(ILogger::INFO),
			'errorHandler' => Expect::structure([
				'handlers' => Expect::arrayOf(
					Expect::anyOf(self::ERROR_HANDLER, self::EXCEPTION_HANDLER, self::FATAL_HANDLER)
				),
				'reportedOnly' => Expect::bool(true),
				'errorTypes' => Expect::int(-1),
			])->required(false),
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

		# registerFallback transformation
		if ($this->config->registerFallback) {
			$this->config->fallback->register = true;
		}
	}

	protected function loadHandlers(): void
	{
		foreach ($this->config->handlers as $name => $implementation) {
			$this->handlers[] = new PriorityDefinition(
				$this->getDefinition(
					$implementation,
					$this->prefix('handler.' . $name)
				),
				is_numeric($name) ? (int)$name : 0
			);
		}
	}

	protected function loadProcessors(): void
	{
		if ($this->config->usePriorityProcessor === true) {
			$this->processors[] = new PriorityDefinition(
				$this->getContainerBuilder()
					->addDefinition($this->prefix('processor.priorityProcessor'))
					->setFactory(PriorityProcessor::class),
				20
			);
		}

		foreach ($this->config->processors as $name => $implementation) {
			$this->processors[] = new PriorityDefinition(
				$this->getDefinition(
					$implementation,
					$this->prefix('processors.' . $name)
				),
				is_numeric($name) ? (int)$name : 0
			);
		}
	}

	/**
	 * @param string|mixed[]|Statement $definition
	 * @param string $name
	 * @return string|Definition
	 */
	protected function getDefinition($definition, string $name)
	{
		$builder = $this->getContainerBuilder();

		# String definition
		if (is_string($definition)) {

			# @alias
			if (Strings::startsWith($definition, '@')) {
				$defName = substr($definition, 1);

				return $builder->hasDefinition($defName) ? $builder->getDefinition($defName) : $definition;
			}

			# Inline definition
			return $builder
				->addDefinition($name, (new ServiceDefinition)->setType($definition))
				->setAutowired(false);
		}

		# Add service and set autowired
		if (is_array($definition)) {
			$definition['autowired'] ??= false;
		}
		$this->compiler->loadDefinitionsFromConfig([
			$name => $definition,
		]);

		return '@' . $name;
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		/** @var ServiceDefinition $logger */
		$logger = $builder->getDefinition($this->prefix('logger'));
		$logger->setArgument('handlers', $this->getDefinitionsByPriority($this->handlers));
		$logger->setArgument('processors', $this->getDefinitionsByPriority($this->processors));

		# Register fallback, if no handlers is set or fallback->register is true
		if (!$this->handlers || $this->config->fallback->register) {
			$builder
				->addDefinition($this->prefix('fallback'))
				->setFactory(FallbackNetteHandler::class, [
					'appName' => $this->config->name,
					'logDir' => $this->config->logDir,
					'defaultFormat' => $this->config->fallback->defaultFormat,
					'priorityFormat' => $this->config->fallback->priorityFormat,
				])
				->setAutowired(false);

			$logger->addSetup('pushHandler', ['@' . $this->prefix('fallback')]);
		}

		# Decorator for LoggerAwareInterface
		foreach ($builder->findByType(LoggerAwareInterface::class) as $service) {
			/** @var ServiceDefinition $service */
			$service->addSetup('setLogger', ['@' . $this->prefix('logger')]);
		}

		if ($this->config->errorHandler) {
			$errorHandlerDefinition = $builder
				->addDefinition(($this->prefix('errorHandler')))
			    ->setFactory(ErrorHandler::class, ['logger' => '@' . $this->prefix('logger')])
				->setAutowired(false);

			if(in_array(self::ERROR_HANDLER, $this->config->errorHandler->handlers, true)){
				$errorHandlerDefinition->addSetup(
					'registerErrorHandler',
					[
						'errorTypes' => $this->config->errorHandler->errorTypes,
						'handleOnlyReportedErrors' => $this->config->errorHandler->reportedOnly
					]
				);
			}
			if(in_array(self::EXCEPTION_HANDLER, $this->config->errorHandler->handlers, true)){
				$errorHandlerDefinition->addSetup('registerExceptionHandler');
			}
			if(in_array(self::FATAL_HANDLER, $this->config->errorHandler->handlers, true)){
				$errorHandlerDefinition->addSetup('registerFatalHandler');
			}
		}
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


		if(!empty($this->config->errorHandler->handlers)){
			$initialize->addBody('$this->getService(?);', [$this->prefix('errorHandler')]);
		}

		$initialize->addBody("// monolog\n($closure)();");
	}

	/**
	 * @param PriorityDefinition[] $array
	 * @return array<Definition|string>
	 */
	private function getDefinitionsByPriority(array $array): array
	{
		usort($array, static fn(PriorityDefinition $a, PriorityDefinition $b) => $b->getPriority() <=> $a->getPriority());
		return array_map(static fn(PriorityDefinition $definition) => $definition->getDefinition(), $array);
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
				'blueScreenRenderer' => $this->config->tracyRenderException
					? $this->prefix('@blueScreenRenderer')
					: null,
				'email' => Debugger::$email,
				'accessPriority' => $this->config->accessPriority,
			])
			->setAutowired(false);

		if ($this->config->tracyRenderException) {
			$builder
				->addDefinition($this->prefix('blueScreenRenderer'))
				->setFactory(BlueScreenRenderer::class, [
					'directory' => $this->config->logDir,
				])
				->setAutowired(false);

			$this->processors[] = new PriorityDefinition(
				$builder
					->addDefinition($this->prefix('processor.tracyException'))
					->setFactory(TracyExceptionProcessor::class, [
						'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
					]),
				100
			);

			if ($this->config->tracyBaseUrl) {
				$this->processors[] = new PriorityDefinition(
					$builder
						->addDefinition($this->prefix('processor.tracyBaseUrl'))
						->setFactory(TracyUrlProcessor::class, [
							'baseUrl' => $this->config->tracyBaseUrl,
							'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
						]),
					10);
			}
		}

		if ($this->config->tracyHook === true) {
			$builder->removeDefinition($this->config->tracyDefinition);
			$builder->addAlias($this->config->tracyDefinition, $this->prefix('tracy'));
			$builder->getDefinition($this->prefix('tracy'))->setAutowired(true);
		}
	}
}
