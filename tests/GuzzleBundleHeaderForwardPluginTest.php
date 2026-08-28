<?php

namespace EncoreLabs\Bundle\GuzzleBundleHeaderForwardPlugin\Tests;

use EncoreLabs\Bundle\GuzzleBundleHeaderForwardPlugin\GuzzleBundleHeaderForwardPlugin;
use EncoreLabs\Bundle\GuzzleBundleHeaderForwardPlugin\Middleware\GuzzleForwardHeaderMiddleware;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

class GuzzleBundleHeaderForwardPluginTest extends TestCase
{
    private const SERVICES_FILE = 'services.yaml';

    private const CLIENT = 'some_client';

    private const MIDDLEWARE_ID = 'guzzle_bundle_header_forward_plugin.middleware.' . self::CLIENT;

    public function testLoadReadsTheBundledServicesFile(): void
    {
        $container = new ContainerBuilder();

        (new GuzzleBundleHeaderForwardPlugin())->load([], $container);

        $loaded = array_filter(
            $container->getResources(),
            static function ($resource) {
                return substr((string) $resource, -strlen(self::SERVICES_FILE)) === self::SERVICES_FILE;
            }
        );

        $this->assertNotEmpty($loaded, self::SERVICES_FILE . ' was not loaded into the container');
    }

    public function testConfigurationAcceptsAHeaderList(): void
    {
        $treeBuilder = new TreeBuilder('header_forward');
        $node = $treeBuilder->getRootNode();

        (new GuzzleBundleHeaderForwardPlugin())->addConfiguration($node);

        $config = (new Processor())->process(
            $treeBuilder->buildTree(),
            [['enabled' => true, 'headers' => ['X-Request-Id']]]
        );

        $this->assertSame(['enabled' => true, 'headers' => ['X-Request-Id']], $config);
    }

    public function testLoadForClientUnshiftsTheMiddlewareOntoTheHandler(): void
    {
        $container = new ContainerBuilder();
        $handler = new Definition(HandlerStack::class);

        (new GuzzleBundleHeaderForwardPlugin())->loadForClient(
            ['enabled' => true, 'headers' => ['X-Request-Id']],
            $container,
            self::CLIENT,
            $handler
        );

        $middleware = $container->getDefinition(self::MIDDLEWARE_ID);
        $this->assertSame(GuzzleForwardHeaderMiddleware::class, $middleware->getClass());
        $this->assertEquals([new Reference('request_stack'), ['X-Request-Id']], $middleware->getArguments());

        $this->assertEquals(
            [['unshift', [new Expression(sprintf("service('%s')", self::MIDDLEWARE_ID)), 'header_forward']]],
            $handler->getMethodCalls()
        );
    }

    /**
     * @dataProvider inertConfigurations
     */
    public function testNothingIsRegisteredWhenThePluginHasNoWorkToDo(array $config): void
    {
        $container = new ContainerBuilder();
        $handler = new Definition(HandlerStack::class);

        (new GuzzleBundleHeaderForwardPlugin())->loadForClient($config, $container, self::CLIENT, $handler);

        $this->assertFalse($container->hasDefinition(self::MIDDLEWARE_ID));
        $this->assertSame([], $handler->getMethodCalls());
    }

    public static function inertConfigurations(): array
    {
        return [
            'disabled' => [['enabled' => false, 'headers' => ['X-Request-Id']]],
            'no headers' => [['enabled' => true, 'headers' => []]],
        ];
    }
}
