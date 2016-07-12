<?php
namespace Wandu\Foundation\Kernels;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Wandu\Config\Config;
use Wandu\Config\Contracts\ConfigInterface;
use Wandu\DI\ContainerInterface;
use Wandu\Foundation\Bridges\WhoopsToPsr7;
use Wandu\Foundation\Contracts\DefinitionInterface;
use Wandu\Foundation\Contracts\HttpErrorHandlerInterface;
use Wandu\Foundation\Contracts\KernelInterface;
use Wandu\Http\Exception\HttpException;
use Wandu\Http\Exception\MethodNotAllowedException;
use Wandu\Http\Exception\NotFoundException;
use Wandu\Http\Psr\Factory\ServerRequestFactory;
use Wandu\Http\Psr\Sender\ResponseSender;
use Wandu\Http\Psr\Stream\StringStream;
use Wandu\Router\Dispatcher;
use Wandu\Router\Exception\MethodNotAllowedException as RouteMethodException;
use Wandu\Router\Exception\RouteNotFoundException;

class HttpRouterKernel implements KernelInterface
{
    /** @var \Wandu\Foundation\Contracts\DefinitionInterface */
    private $definition;

    /**
     * @param \Wandu\Foundation\Contracts\DefinitionInterface $definition
     */
    public function __construct(DefinitionInterface $definition)
    {
        $this->definition = $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $app)
    {
        $app->instance(Config::class, new Config($this->definition->configs()));
        $app->alias(ConfigInterface::class, Config::class);
        $app->alias('config', Config::class);
        $this->definition->providers($app);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ContainerInterface $app)
    {
        /* @var \Wandu\Config\Contracts\ConfigInterface $config*/
        $config = $app->get(ConfigInterface::class);
        
        /* @var \Psr\Http\Message\ServerRequestInterface $request */
        $request = $app->get(ServerRequestFactory::class)->createFromGlobals();

        try {
            $response = $this->dispatch($app->get(Dispatcher::class), $request);
        } catch (HttpException $exception) {
            /* @var \Wandu\Foundation\Contracts\HttpErrorHandlerInterface $handler */
            $handler = $app->get(HttpErrorHandlerInterface::class);
            $response = $handler->handle($request, $exception);
        } catch (Throwable $exception) {
            // if Debug Mode, make prettyfy response.
            if ($config->get('debug', true)) {
                /* @var \Wandu\Foundation\Bridges\WhoopsToPsr7 $prettifier */
                $prettifier = $app->get(WhoopsToPsr7::class);
                $response = $prettifier->responsify($exception);
            } else {
                /* @var \Wandu\Foundation\Contracts\HttpErrorHandlerInterface $handler */
                $handler = $app->get(HttpErrorHandlerInterface::class);
                $response = $handler->handle($request, $exception);
            }
            if (!$response->getBody()) {
                $body = $response->getReasonPhrase();
                $response = $response->withBody(new Stringstream($body));
            }
        }

        /* @var \Wandu\Http\Psr\Sender\ResponseSender $sender */
        $sender = $app->get(ResponseSender::class);
        $sender->sendToGlobal($response);
        return 0;
    }

    /**
     * @param \Wandu\Router\Dispatcher $dispatcher
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Wandu\Http\Exception\MethodNotAllowedException
     * @throws \Wandu\Http\Exception\NotFoundException
     */
    protected function dispatch(Dispatcher $dispatcher, ServerRequestInterface $request)
    {
        $dispatcher = $dispatcher->withRoutes($this->definition);
        try {
            return $dispatcher->dispatch($request);
        } catch (RouteNotFoundException $exception) {
            throw new NotFoundException();
        } catch (RouteMethodException $exception) {
            throw new MethodNotAllowedException();
        }
    }
}
