<?php

declare(strict_types=1);

namespace spec\PhpSpec\Wrapper\Subject;

use PhpSpec\CodeAnalysis\AccessInspector;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\ExceptionFactory;
use PhpSpec\Exception\Fracture\PropertyNotFoundException;
use PhpSpec\Wrapper\Subject\WrappedObject;
use PhpSpec\Wrapper\Wrapper;
use PhpSpec\Wrapper\Subject;

use PhpSpec\Loader\Node\ExampleNode;

use Symfony\Component\EventDispatcher\EventDispatcherInterface as Dispatcher;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CallerSpec extends ObjectBehavior
{
    public function let(
        WrappedObject $wrappedObject,
        ExampleNode $example,
        Dispatcher $dispatcher,
        ExceptionFactory $exceptions,
        Wrapper $wrapper,
        AccessInspector $accessInspector,
        Subject $subject
    ) {
        $this->beConstructedWith(
            $wrappedObject,
            $example,
            $dispatcher,
            $exceptions,
            $wrapper,
            $accessInspector
        );
        $wrapper->wrap(Argument::cetera())->willReturn($subject);

        $wrappedObject->isInstantiated()->willReturn(false);
        $wrappedObject->getClassName()->willReturn(null);
        $wrappedObject->getInstance()->willReturn(null);
        $exceptions->propertyNotFound(Argument::cetera())->willReturn(new PropertyNotFoundException('Message', 'subject', 'prop'));

        $accessInspector->isMethodCallable(Argument::cetera())->willReturn(false);
    }

    public function it_dispatches_method_call_events(
        Dispatcher $dispatcher,
        WrappedObject $wrappedObject,
        AccessInspector $accessInspector
    ) {
        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn(new \ArrayObject());

        $accessInspector->isMethodCallable(Argument::type('ArrayObject'), 'count')->willReturn(true);

        $dispatcher->dispatch(
            'beforeMethodCall',
            Argument::type('PhpSpec\Event\MethodCallEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterMethodCall',
            Argument::type('PhpSpec\Event\MethodCallEvent')
        )->shouldBeCalled();

        $this->call('count');
    }

    public function it_sets_a_property_on_the_wrapped_object(
        WrappedObject $wrappedObject,
        AccessInspector $accessInspector
    ) {
        $obj = new \stdClass();
        $obj->id = 1;

        $accessInspector->isPropertyWritable(
            Argument::type('stdClass'),
            'id'
        )->willReturn('true');

        $accessInspector->isPropertyReadable(
            Argument::type('stdClass'),
            'id'
        )->willReturn('true');

        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);

        $this->set('id', 2);
        if ($obj->id !== 2) {
            throw new FailureException();
        }
    }

    public function it_proxies_method_calls_to_wrapped_object(
        \ArrayObject $obj,
        WrappedObject $wrappedObject,
        AccessInspector $accessInspector
    ) {
        $obj->asort()->shouldBeCalled();

        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);

        $accessInspector->isMethodCallable(Argument::type('ArrayObject'), 'asort')->willReturn(true);

        $this->call('asort');
    }

    public function it_delegates_throwing_class_not_found_exception(WrappedObject $wrappedObject, ExceptionFactory $exceptions)
    {
        $wrappedObject->isInstantiated()->willReturn(false);
        $wrappedObject->getClassName()->willReturn('Foo');

        $exceptions->classNotFound('Foo')
            ->willReturn(new \PhpSpec\Exception\Fracture\ClassNotFoundException(
                'Class "Foo" does not exist.',
                '"Foo"'
            ))
            ->shouldBeCalled();

        $this->shouldThrow('\PhpSpec\Exception\Fracture\ClassNotFoundException')
            ->duringGetWrappedObject();
    }

    public function it_delegates_throwing_method_not_found_exception(
        WrappedObject $wrappedObject,
        ExceptionFactory $exceptions,
        AccessInspector $accessInspector
    ) {
        $obj = new \ArrayObject();

        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);
        $wrappedObject->getClassName()->willReturn('ArrayObject');

        $accessInspector->isMethodCallable($obj, 'foo')->willReturn(false);

        $exceptions->methodNotFound('ArrayObject', 'foo', [])
            ->willReturn(new \PhpSpec\Exception\Fracture\MethodNotFoundException(
                'Method "foo" not found.',
                $obj,
                '"ArrayObject::foo"',
                []
            ))
            ->shouldBeCalled();

        $this->shouldThrow('\PhpSpec\Exception\Fracture\MethodNotFoundException')
            ->duringCall('foo');
    }

    public function it_delegates_throwing_method_not_found_exception_for_constructor(WrappedObject $wrappedObject, ExceptionFactory $exceptions, \stdClass $argument)
    {
        $obj = new ExampleClass();

        $wrappedObject->isInstantiated()->willReturn(false);
        $wrappedObject->getInstance()->willReturn(null);
        $wrappedObject->getArguments()->willReturn([$argument]);
        $wrappedObject->getClassName()->willReturn('spec\PhpSpec\Wrapper\Subject\ExampleClass');
        $wrappedObject->getFactoryMethod()->willReturn(null);

        $exceptions->methodNotFound('spec\PhpSpec\Wrapper\Subject\ExampleClass', '__construct', [$argument])
            ->willReturn(new \PhpSpec\Exception\Fracture\MethodNotFoundException(
                'Method "__construct" not found.',
                $obj,
                '"ExampleClass::__construct"',
                []
                ))
            ->shouldBeCalled();

        $this->shouldThrow('\PhpSpec\Exception\Fracture\MethodNotFoundException')
            ->duringCall('__construct');
    }

    public function it_delegates_throwing_named_constructor_not_found_exception(WrappedObject $wrappedObject, ExceptionFactory $exceptions)
    {
        $obj = new \ArrayObject();
        $arguments = ['firstname', 'lastname'];

        $wrappedObject->isInstantiated()->willReturn(false);
        $wrappedObject->getInstance()->willReturn(null);
        $wrappedObject->getClassName()->willReturn('ArrayObject');
        $wrappedObject->getFactoryMethod()->willReturn('register');
        $wrappedObject->getArguments()->willReturn($arguments);

        $exceptions->namedConstructorNotFound('ArrayObject', 'register', $arguments)
            ->willReturn(new \PhpSpec\Exception\Fracture\NamedConstructorNotFoundException(
                'Named constructor "register" not found.',
                $obj,
                '"ArrayObject::register"',
                []
            ))
            ->shouldBeCalled();

        $this->shouldThrow('\PhpSpec\Exception\Fracture\NamedConstructorNotFoundException')
            ->duringCall('foo');
    }

    public function it_delegates_throwing_method_not_visible_exception(
        WrappedObject $wrappedObject,
        ExceptionFactory $exceptions,
        AccessInspector $accessInspector
    ) {
        $obj = new ExampleClass();

        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);
        $wrappedObject->getClassName()->willReturn('spec\PhpSpec\Wrapper\Subject\ExampleClass');

        $accessInspector->isMethodCallable($obj, 'privateMethod')->willReturn(false);

        $exceptions->methodNotVisible('spec\PhpSpec\Wrapper\Subject\ExampleClass', 'privateMethod', [])
            ->willReturn(new \PhpSpec\Exception\Fracture\MethodNotVisibleException(
                'Method "privateMethod" not visible.',
                $obj,
                '"ExampleClass::privateMethod"',
                []
            ))
            ->shouldBeCalled();

        $this->shouldThrow('\PhpSpec\Exception\Fracture\MethodNotVisibleException')
            ->duringCall('privateMethod');
    }

    public function it_delegates_throwing_property_not_found_exception(
        WrappedObject $wrappedObject,
        ExceptionFactory $exceptions,
        AccessInspector $accessInspector
    ) {
        $obj = new ExampleClass();

        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);

        $accessInspector->isPropertyWritable($obj, 'nonExistentProperty')->willReturn(false);

        $exceptions->propertyNotFound($obj, 'nonExistentProperty')
            ->willReturn(new \PhpSpec\Exception\Fracture\PropertyNotFoundException(
                'Property "nonExistentProperty" not found.',
                $obj,
                'nonExistentProperty'
            ))
            ->shouldBeCalled();

        $this->shouldThrow('\PhpSpec\Exception\Fracture\PropertyNotFoundException')
            ->duringSet('nonExistentProperty', 'any value');
    }

    public function it_delegates_throwing_calling_method_on_non_object_exception(ExceptionFactory $exceptions)
    {
        $exceptions->callingMethodOnNonObject('foo')
            ->willReturn(new \PhpSpec\Exception\Wrapper\SubjectException(
                'Call to a member function "foo()" on a non-object.'
            ))
            ->shouldBeCalled();

        $this->shouldThrow('\PhpSpec\Exception\Wrapper\SubjectException')
            ->duringCall('foo');
    }

    public function it_delegates_throwing_setting_property_on_non_object_exception(ExceptionFactory $exceptions)
    {
        $exceptions->settingPropertyOnNonObject('foo')
            ->willReturn(new \PhpSpec\Exception\Wrapper\SubjectException(
                'Setting property "foo" on a non-object.'
            ))
            ->shouldBeCalled();
        $this->shouldThrow('\PhpSpec\Exception\Wrapper\SubjectException')
            ->duringSet('foo');
    }

    public function it_delegates_throwing_getting_property_on_non_object_exception(ExceptionFactory $exceptions)
    {
        $exceptions->gettingPropertyOnNonObject('foo')
            ->willReturn(new \PhpSpec\Exception\Wrapper\SubjectException(
                'Getting property "foo" on a non-object.'
            ))
            ->shouldBeCalled();

        $this->shouldThrow('\PhpSpec\Exception\Wrapper\SubjectException')
            ->duringGet('foo');
    }
}

class ExampleClass
{
    private function privateMethod()
    {
    }
}
