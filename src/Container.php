<?php
namespace Wandu\DI;

use Closure;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionObject;
use RuntimeException;
use Wandu\DI\Exception\CannotChangeException;
use Wandu\DI\Exception\CannotInjectException;
use Wandu\DI\Exception\CannotResolveException;
use Wandu\DI\Exception\NullReferenceException;
use Wandu\Reflection\ReflectionCallable;

class Container implements ContainerInterface
{
    /** @var array */
    protected $keys = [];

    /** @var array */
    protected $closures = [];

    /** @var array */
    protected $instances = [];

    /** @var array */
    protected $aliases = [];

    /** @var array */
    protected $bind = [];

    /** @var array ref. Pimple */
    protected $frozen = [];

    /** @var \Wandu\DI\ServiceProviderInterface[] */
    protected $providers = [];

    public function __construct()
    {
        $this->instance('container', $this)->freeze('container');
        $this->alias(ContainerInterface::class, 'container')
            ->freeze(ContainerInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($name, $value)
    {
        $this->instance($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($name)
    {
        $this->destroy($name);
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return isset($this->keys[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($name)
    {
        if (isset($this->frozen[$name])) {
            throw new CannotChangeException($name);
        }
        unset(
            $this->keys[$name],
            $this->closures[$name],
            $this->instances[$name],
            $this->aliases[$name],
            $this->bind[$name]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (!isset($this->keys[$name])) {
            throw new NullReferenceException($name);
        }
        $this->freeze($name);
        if ('alias' === $key = $this->keys[$name]) {
            return $this->get($this->aliases[$name]);
        }
        if (!isset($this->instances[$name])) {
            switch ($key) {
                case 'closure':
                    $this->instances[$name] = call_user_func($this->closures[$name], $this);
                    break;
                case 'bind':
                    $this->instances[$name] = $this->create($this->bind[$name]);
                    break;
                case 'wire':
                    $this->instances[$name] = $this->create($this->bind[$name]);
                    $this->inject($this->instances[$name]);
                    break;
            }
        }
        return $this->instances[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function instance($name, $value)
    {
        $this->destroy($name);
        $this->keys[$name] = 'instance';
        $this->instances[$name] = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function closure($name, Closure $handler)
    {
        $this->destroy($name);
        $this->keys[$name] = 'closure';
        $this->closures[$name] = $handler;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function bind($name, $class = null)
    {
        if (!isset($class)) {
            $class = $name;
        }
        $this->destroy($name);
        $this->keys[$name] = 'bind';
        $this->bind[$name] = $class;
        if ($name !== $class) {
            $this->alias($class, $name);
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function alias($name, $origin)
    {
        $this->destroy($name);
        $this->keys[$name] = 'alias';
        $this->aliases[$name] = $origin;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function wire($name, $class = null)
    {
        $this->bind($name, $class);
        $this->keys[$name] = 'wire';
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function extend($name, Closure $handler)
    {
        if (!isset($this->keys[$name])) {
            throw new NullReferenceException($name);
        }
        if (isset($this->aliases[$name])) {
            $this->extend($this->aliases[$name], $handler);
            return $this;
        }
        if (isset($this->instances[$name])) {
            $this->instances[$name] = call_user_func($handler, $this->instances[$name]);
        }
        if (isset($this->closures[$name])) {
            $closure = $this->closures[$name];
            $this->closures[$name] = function () use ($closure, $handler) {
                return call_user_func($handler, call_user_func($closure, $this));
            };
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function register(ServiceProviderInterface $provider)
    {
        $provider->register($this);
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function freeze($name)
    {
        $this->frozen[$name] = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function create($class, array $arguments = [])
    {
        $reflectionClass = new ReflectionClass($class);
        $reflectionMethod = $reflectionClass->getConstructor();
        if ($reflectionMethod) {
            try {
                $parameters = $this->getParameters($reflectionMethod, $arguments);
            } catch (RuntimeException $e) {
                throw new CannotResolveException($class);
            }
        } else {
            $parameters = [];
        }
        return $reflectionClass->newInstanceArgs($parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function call(callable $callee, array $arguments = [])
    {
        return call_user_func_array(
            $callee,
            $this->getParameters(new ReflectionCallable($callee), $arguments)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function inject($object, array $parameters = [])
    {
        $reflectionObject = new ReflectionObject($object);

        foreach ($reflectionObject->getProperties() as $property) {
            $comment = $property->getDocComment();
            if (strpos($comment, '@Autowired') !== false) {
                $className = $this->getClassNameFromDocComment($comment);
                if (isset($className)) {
                    $property->setAccessible(true);
                    try {
                        $property->setValue($object, $this->get($className));
                    } catch (NullReferenceException $e) {
                        throw new CannotInjectException(get_class($object), $property->getName());
                    }
                } else {
                    throw new CannotInjectException(get_class($object), $property->getName());
                }
            } elseif (isset($parameters[$propertyName = $property->getName()])) {
                $property->setAccessible(true);
                $property->setValue($object, $parameters[$propertyName]);
            }
        }
    }

    /**
     * @param string $comment
     * @return string
     */
    protected function getClassNameFromDocComment($comment)
    {
        $varPosition = strpos($comment, '@var');
        if ($varPosition === false) {
            return null;
        }
        preg_match('/^([a-zA-Z0-9\\\\]+)/', ltrim(substr($comment, $varPosition + 4)), $matches);
        $className =  $matches[0];
        if ($className[0] === '\\') {
            $className = substr($className, 1);
        }
        return $className;
    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionFunction
     * @param array $arguments
     * @return array
     */
    protected function getParameters(ReflectionFunctionAbstract $reflectionFunction, array $arguments)
    {
        $parametersToReturn = [];
        $parameters = $this->getOnlySeqArray($arguments);
        foreach ($reflectionFunction->getParameters() as $param) {
            if (isset($arguments[$param->getName()])) {
                $parametersToReturn[] = $arguments[$param->getName()];
            } elseif ($paramClassReflection = $param->getClass()) {
                $parametersToReturn[] = $this->get($paramClassReflection->getName());
            } elseif (count($arguments)) {
                $parametersToReturn[] = array_shift($parameters);
            } else {
                throw new RuntimeException('Fail to get parameter.');
            }
        }
        return array_merge($parametersToReturn, $parameters);
    }

    /**
     * @param array $array
     * @return array
     */
    protected function getOnlySeqArray(array $array)
    {
        $arrayToReturn = [];
        foreach ($array as $key => $item) {
            if (is_int($key)) {
                $arrayToReturn[] = $item;
            }
        }
        return $arrayToReturn;
    }

    /**
     * {@inheritdoc}
     */
    public function __call($name, array $arguments)
    {
        return $this->call($this->get($name), ...$arguments);
    }

    /**
     * @return self
     */
    public function boot()
    {
        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }
        return $this;
    }
}
