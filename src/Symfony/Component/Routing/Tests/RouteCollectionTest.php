<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Config\Resource\FileResource;

class RouteCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testRoute()
    {
        $collection = new RouteCollection();
        $route = new Route('/foo');
        $collection->add('foo', $route);
        $this->assertEquals(array('foo' => $route), $collection->all(), '->add() adds a route');
        $this->assertEquals($route, $collection->get('foo'), '->get() returns a route by name');
        $this->assertNull($collection->get('bar'), '->get() returns null if a route does not exist');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAddInvalidRoute()
    {
        $collection = new RouteCollection();
        $route = new Route('/foo');
        $collection->add('f o o', $route);
    }

    public function testOverridenRoute()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));
        $collection->add('foo', new Route('/foo1'));

        $this->assertEquals('/foo1', $collection->get('foo')->getPattern());
    }

    public function testDeepOverridenRoute()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/foo1'));

        $collection2 = new RouteCollection();
        $collection2->add('foo', new Route('/foo2'));

        $collection1->addCollection($collection2);
        $collection->addCollection($collection1);

        $this->assertEquals('/foo2', $collection1->get('foo')->getPattern());
        $this->assertEquals('/foo2', $collection->get('foo')->getPattern());
    }

    public function testIteratorWithOverridenRoutes()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection->addCollection($collection1);
        $collection1->add('foo', new Route('/foo1'));

        $this->assertEquals('/foo1', $this->getFirstNamedRoute($collection, 'foo')->getPattern());
    }

    protected function getFirstNamedRoute(RouteCollection $routeCollection, $name)
    {
        foreach ($routeCollection as $key => $route) {
            if ($route instanceof RouteCollection) {
                return $this->getFirstNamedRoute($route, $name);
            }

            if ($name === $key) {
                return $route;
            }
        }
    }

    public function testAddCollection()
    {
        if (!class_exists('Symfony\Component\Config\Resource\FileResource')) {
            $this->markTestSkipped('The "Config" component is not available');
        }

        $collection = new RouteCollection();
        $collection->add('foo', $foo = new Route('/foo'));
        $collection1 = new RouteCollection();
        $collection1->add('foo', $foo1 = new Route('/foo1'));
        $collection1->add('bar', $bar1 = new Route('/bar1'));
        $collection->addCollection($collection1);
        $this->assertEquals(array('foo' => $foo1, 'bar' => $bar1), $collection->all(), '->addCollection() adds routes from another collection');

        $collection = new RouteCollection();
        $collection->add('foo', $foo = new Route('/foo'));
        $collection1 = new RouteCollection();
        $collection1->add('foo', $foo1 = new Route('/foo1'));
        $collection->addCollection($collection1, '/{foo}', array('foo' => 'foo'), array('foo' => '\d+'), array('foo' => 'bar'));
        $this->assertEquals('/{foo}/foo1', $collection->get('foo')->getPattern(), '->addCollection() can add a prefix to all merged routes');
        $this->assertEquals(array('foo' => 'foo'), $collection->get('foo')->getDefaults(), '->addCollection() can add a prefix to all merged routes');
        $this->assertEquals(array('foo' => '\d+'), $collection->get('foo')->getRequirements(), '->addCollection() can add a prefix to all merged routes');
        $this->assertEquals(
            array('foo' => 'bar', 'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler'),
            $collection->get('foo')->getOptions(), '->addCollection() can add an option to all merged routes'
        );

        $collection = new RouteCollection();
        $collection->addResource($foo = new FileResource(__DIR__.'/Fixtures/foo.xml'));
        $collection1 = new RouteCollection();
        $collection1->addResource($foo1 = new FileResource(__DIR__.'/Fixtures/foo1.xml'));
        $collection->addCollection($collection1);
        $this->assertEquals(array($foo, $foo1), $collection->getResources(), '->addCollection() merges resources');
    }

    public function testAddPrefix()
    {
        $collection = new RouteCollection();
        $collection->add('foo', $foo = new Route('/foo'));
        $collection->add('bar', $bar = new Route('/bar'));
        $collection->addPrefix('/{admin}', array('admin' => 'admin'), array('admin' => '\d+'), array('foo' => 'bar'));
        $this->assertEquals('/{admin}/foo', $collection->get('foo')->getPattern(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals('/{admin}/bar', $collection->get('bar')->getPattern(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals(array('admin' => 'admin'), $collection->get('foo')->getDefaults(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals(array('admin' => 'admin'), $collection->get('bar')->getDefaults(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals(array('admin' => '\d+'), $collection->get('foo')->getRequirements(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals(array('admin' => '\d+'), $collection->get('bar')->getRequirements(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals(
            array('foo' => 'bar', 'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler'),
            $collection->get('foo')->getOptions(), '->addPrefix() adds an option to all routes'
        );
        $this->assertEquals(
            array('foo' => 'bar', 'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler'),
            $collection->get('bar')->getOptions(), '->addPrefix() adds an option to all routes'
        );
    }

    public function testAddPrefixOverridesDefaultsAndRequirements()
    {
        $collection = new RouteCollection();
        $collection->add('foo', $foo = new Route('/foo'));
        $collection->add('bar', $bar = new Route('/bar', array(), array('_scheme' => 'http')));
        $collection->addPrefix('/admin', array(), array('_scheme' => 'https'));

        $this->assertEquals('https', $collection->get('foo')->getRequirement('_scheme'), '->addPrefix() overrides existing requirements');
        $this->assertEquals('https', $collection->get('bar')->getRequirement('_scheme'), '->addPrefix() overrides existing requirements');
    }

    public function testAddCollectionOverridesDefaultsAndRequirements()
    {
        $imported = new RouteCollection();
        $imported->add('foo', $foo = new Route('/foo'));
        $imported->add('bar', $bar = new Route('/bar', array(), array('_scheme' => 'http')));

        $collection = new RouteCollection();
        $collection->addCollection($imported, null, array(), array('_scheme' => 'https'));

        $this->assertEquals('https', $collection->get('foo')->getRequirement('_scheme'), '->addCollection() overrides existing requirements');
        $this->assertEquals('https', $collection->get('bar')->getRequirement('_scheme'), '->addCollection() overrides existing requirements');
    }

    public function testResource()
    {
        if (!class_exists('Symfony\Component\Config\Resource\FileResource')) {
            $this->markTestSkipped('The "Config" component is not available');
        }

        $collection = new RouteCollection();
        $collection->addResource($foo = new FileResource(__DIR__.'/Fixtures/foo.xml'));
        $this->assertEquals(array($foo), $collection->getResources(), '->addResources() adds a resource');
    }
}
