<?php declare(strict_types=1);
/*
 * This file is part of The Framework Routing Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\Routing;

use Closure;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;

/**
 * Class Route.
 */
class Route
{
	protected Router $router;
	protected string $origin;
	protected string $path;
	protected Closure | string $action;
	/**
	 * @var array|string[]
	 */
	protected array $actionParams = [];
	protected ?string $name = null;
	/**
	 * @var array|mixed[]
	 */
	protected array $options = [];

	/**
	 * Route constructor.
	 *
	 * @param Router         $router A Router instance
	 * @param string         $origin URL Origin. A string in the following format:
	 *                               {scheme}://{hostname}[:{port}]
	 * @param string         $path   URL Path. A string starting with '/'
	 * @param Closure|string $action The action
	 */
	public function __construct(
		Router $router,
		string $origin,
		string $path,
		Closure | string $action
	) {
		$this->router = $router;
		$this->setOrigin($origin);
		$this->setPath($path);
		$this->setAction($action);
	}

	/**
	 * Gets the URL Origin.
	 *
	 * @param string ...$params Parameters to fill the URL Origin placeholders
	 *
	 * @return string
	 */
	public function getOrigin(string ...$params) : string
	{
		if ($params) {
			return $this->router->fillPlaceholders($this->origin, ...$params);
		}
		return $this->origin;
	}

	/**
	 * @param string $origin
	 *
	 * @return static
	 */
	protected function setOrigin(string $origin) : static
	{
		$this->origin = \ltrim($origin, '/');
		return $this;
	}

	/**
	 * Gets the URL.
	 *
	 * @param array|string[] $origin_params Parameters to fill the URL Origin placeholders
	 * @param array|string[] $path_params   Parameters to fill the URL Path placeholders
	 *
	 * @return string
	 */
	public function getURL(array $origin_params = [], array $path_params = []) : string
	{
		return $this->getOrigin(...$origin_params) . $this->getPath(...$path_params);
	}

	/**
	 * @return array|mixed[]
	 */
	#[Pure]
	public function getOptions() : array
	{
		return $this->options;
	}

	/**
	 * @param array|mixed[] $options
	 *
	 * @return static
	 */
	public function setOptions(array $options) : static
	{
		$this->options = $options;
		return $this;
	}

	#[Pure]
	public function getName() : ?string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 *
	 * @return static
	 */
	public function setName(string $name) : static
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * @param string $path
	 *
	 * @return static
	 */
	public function setPath(string $path) : static
	{
		$this->path = '/' . \trim($path, '/');
		return $this;
	}

	/**
	 * Gets the URL Path.
	 *
	 * @param string ...$params Parameters to fill the URL Path placeholders
	 *
	 * @return string
	 */
	public function getPath(string ...$params) : string
	{
		if ($params) {
			return $this->router->fillPlaceholders($this->path, ...$params);
		}
		return $this->path;
	}

	#[Pure]
	public function getAction() : Closure | string
	{
		return $this->action;
	}

	/**
	 * Sets the Route Action.
	 *
	 * @param Closure|string $action A \Closure or a string in the format of the
	 *                               __METHOD__
	 *                               constant. Example: App\Blog::show/0/2/1. Where /0/2/1
	 *                               is the method parameters order
	 *
	 * @see setActionParams
	 * @see run
	 *
	 * @return static
	 */
	public function setAction(Closure | string $action) : static
	{
		$this->action = \is_string($action) ? \trim($action, '\\') : $action;
		return $this;
	}

	/**
	 * @return array|string[]
	 */
	#[Pure]
	public function getActionParams() : array
	{
		return $this->actionParams;
	}

	/**
	 * Sets the Action parameters.
	 *
	 * @param array|string[] $params The parameters. Note that the indexes set the order of how the
	 *                               parameters are passed to the Action
	 *
	 * @see setAction
	 *
	 * @return static
	 */
	public function setActionParams(array $params) : static
	{
		\ksort($params);
		$this->actionParams = $params;
		return $this;
	}

	/**
	 * Run the Route Action.
	 *
	 * @param mixed ...$construct Class constructor parameters
	 *
	 * @throws Exception if class or method not exists
	 *
	 * @return mixed The action returned value
	 */
	public function run(mixed ...$construct) : mixed
	{
		$action = $this->getAction();
		if ($action instanceof Closure) {
			return $action($this->getActionParams(), ...$construct);
		}
		if ( ! \str_contains($action, '::')) {
			$action .= '::' . $this->router->getDefaultRouteActionMethod();
		}
		[$classname, $action] = \explode('::', $action, 2);
		[$action, $params] = $this->extractActionAndParams($action);
		if ( ! \class_exists($classname)) {
			throw new Exception("Class not exists: {$classname}");
		}
		/**
		 * @var RouteAction $class
		 */
		$class = new $classname(...$construct);
		if ( ! \method_exists($class, $action)) {
			throw new Exception(
				"Class method not exists: {$classname}::{$action}"
			);
		}
		$class->actionMethod = $action;
		$class->actionParams = $params;
		$class->actionRun = false;
		$response = null;
		if (\method_exists($class, 'beforeAction')) {
			$response = $class->beforeAction();
		}
		if ($response === null) {
			$class->actionRun = true;
			$response = $class->{$action}(...$params);
		}
		if (\method_exists($class, 'afterAction')) {
			$response = $class->afterAction($response);
		}
		return $response;
	}

	/**
	 * @param string $action An action part like: index/0/2/1
	 *
	 * @throws InvalidArgumentException for undefined action parameter
	 *
	 * @return array|mixed[]
	 */
	protected function extractActionAndParams(string $action) : array
	{
		if (\strpos($action, '/') === false) {
			return [$action, []];
		}
		$params = \explode('/', $action);
		$action = $params[0];
		unset($params[0]);
		if ($params) {
			$action_params = $this->getActionParams();
			$params = \array_values($params);
			foreach ($params as $index => $param) {
				if ( ! \array_key_exists($param, $action_params)) {
					throw new InvalidArgumentException("Undefined action parameter: {$param}");
				}
				$params[$index] = $action_params[$param];
			}
		}
		return [
			$action,
			$params,
		];
	}
}
