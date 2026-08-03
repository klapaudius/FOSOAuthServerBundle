<?php

declare(strict_types=1);

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\OAuthServerBundle\Tests\Form\Handler;

use FOS\OAuthServerBundle\Form\Handler\AuthorizeFormHandler;
use FOS\OAuthServerBundle\Form\Model\Authorize;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AuthorizeFormHandlerTest.
 *
 * @author Nikola Petkanski <nikola@petkanski.com>
 */
#[AllowMockObjectsWithoutExpectations]
class AuthorizeFormHandlerTest extends TestCase
{
    protected FormInterface|MockObject $form;
    protected Request|MockObject $request;
    protected ParameterBag|MockObject $requestRequest;
    protected ContainerInterface|MockObject $container;
    protected AuthorizeFormHandler $instance;

    public function setUp(): void
    {
        $this->form = $this->getMockBuilder(FormInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->request = new Request();

        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->instance = new AuthorizeFormHandler($this->form, $this->request);
        $this->instance->setContainer($this->container);

        $_GET = [];

        parent::setUp();
    }

    public function testConstructWillThrowException(): void
    {
        $exceptionMessage = sprintf(
            'Argument 2 of %s must be an instanceof RequestStack or Request',
            AuthorizeFormHandler::class
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new AuthorizeFormHandler($this->form, new \stdClass());
    }

    public function testIsAcceptedWillProxyValueToFormData(): void
    {
        $data = new \stdClass();
        $data->accepted = \random_bytes(10);

        $this->form
            ->expects($this->once())
            ->method('getData')
            ->with()
            ->willReturn($data)
        ;

        $this->assertSame($data->accepted, $this->instance->isAccepted());
    }

    public function testIsRejectedWillNegateAcceptedValueFromFormData(): void
    {
        $dataWithAcceptedValueFalse = new \stdClass();
        $dataWithAcceptedValueFalse->accepted = false;

        $dataWithAcceptedValueTrue = new \stdClass();
        $dataWithAcceptedValueTrue->accepted = true;

        $this->form
            ->expects($this->exactly(2))
            ->method('getData')
            ->willReturnOnConsecutiveCalls(
                $dataWithAcceptedValueFalse,
                $dataWithAcceptedValueTrue
            )
        ;

        $this->assertTrue($this->instance->isRejected());
        $this->assertFalse($this->instance->isRejected());
    }

    public function testGetScopeWillProxyValueToFormData(): void
    {
        $data = new \stdClass();
        $data->scope = \random_bytes(10);

        $this->form
            ->expects($this->once())
            ->method('getData')
            ->with()
            ->willReturn($data)
        ;

        $this->assertSame($data->scope, $this->instance->getScope());
    }

    public function testGetCurrentRequestWillReturnRequestObject(): void
    {
        $method = $this->getReflectionMethod('getCurrentRequest');
        $this->assertSame($this->request, $method->invoke($this->instance));
    }

    public function testGetCurrentRequestWillReturnCurrentRequestFromRequestStack(): void
    {
        $requestStack = $this->getMockBuilder(RequestStack::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->instance = new AuthorizeFormHandler($this->form, $requestStack);

        $request = new Request();

        $requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->with()
            ->willReturn($request)
        ;

        $method = $this->getReflectionMethod('getCurrentRequest');
        $this->assertSame($request, $method->invoke($this->instance));
    }

    public function testGetCurrentRequestWillReturnRequestServiceFromContainerIfNoneIsSet(): void
    {
        $this->instance = new AuthorizeFormHandler($this->form, null);
        $this->instance->setContainer($this->container);

        $randomData = new Request();

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->willReturn($randomData)
        ;

        $method = $this->getReflectionMethod('getCurrentRequest');
        $this->assertSame($randomData, $method->invoke($this->instance));
    }

    /**
     * @TODO Fix this behavior. This method MUST not modify $_GET.
     */
    public function testOnSuccessWillReplaceGETSuperGlobal(): void
    {
        $method = $this->getReflectionMethod('onSuccess');

        $data = new \stdClass();
        $data->client_id = \random_bytes(10);
        $data->response_type = \random_bytes(10);
        $data->redirect_uri = \random_bytes(10);
        $data->state = \random_bytes(10);
        $data->scope = \random_bytes(10);
        $data->nonce = \random_bytes(10);

        $this->form
            ->expects($this->exactly(6))
            ->method('getData')
            ->with()
            ->willReturn($data)
        ;

        $_GET = [];

        $expectedSuperGlobalValue = [
            'client_id' => $data->client_id,
            'response_type' => $data->response_type,
            'redirect_uri' => $data->redirect_uri,
            'state' => $data->state,
            'scope' => $data->scope,
            'nonce' => $data->nonce,
        ];

        $this->assertNull($method->invoke($this->instance));

        $this->assertSame($expectedSuperGlobalValue, $_GET);
    }

    public function testProcessWillReturnFalseIfRequestIsNull(): void
    {
        $this->instance = new AuthorizeFormHandler($this->form, null);
        $this->instance->setContainer($this->container);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->willReturn(null)
        ;

        $this->assertFalse($this->instance->process());
    }

    public function testProcessWillSetFormData(): void
    {
        $this->request->request->set('accepted', true);

        $dataMock = [
            \random_bytes(10),
            \random_bytes(10),
        ];
        $this->request->query->set( "0", $dataMock[0]);
        $this->request->query->set( "1", $dataMock[1]);

        $this->form
            ->expects($this->once())
            ->method('setData')
            ->with(new Authorize(
                true,
                $dataMock
            ))
            ->willReturn($this->form)
        ;
        $this->form
            ->expects($this->never())
            ->method('isSubmitted')
        ;
        $this->form
            ->expects($this->never())
            ->method('isValid')
        ;

        $this->assertFalse($this->instance->process());
    }

    public function testProcessWillHandleRequestOnPost(): void
    {
        $this->request->request->set('accepted', true);
        $this->request->setMethod('POST');

        $dataMock = [
            \random_bytes(10),
            \random_bytes(10),
        ];
        $this->request->query->set( "0", $dataMock[0]);
        $this->request->query->set( "1", $dataMock[1]);

        $this->form
            ->expects($this->once())
            ->method('setData')
            ->with(new Authorize(
                true,
                $dataMock
            ))
            ->willReturn($this->form)
        ;

        $this->form
            ->expects($this->once())
            ->method('handleRequest')
            ->with($this->request)
            ->willReturn($this->form)
        ;

        $this->form
            ->expects($this->once())
            ->method('isSubmitted')
            ->with()
            ->willReturn(true)
        ;

        $this->form
            ->expects($this->once())
            ->method('isValid')
            ->with()
            ->willReturn(false)
        ;

        $this->assertFalse($this->instance->process());
    }

    public function testProcessWillHandleRequestOnPostAndWillProcessDataIfFormIsValid(): void
    {
        $this->request->request->set('accepted', true);
        $this->request->setMethod('POST');

        $query = new \stdClass();
        $query->client_id = \random_bytes(10);
        $query->response_type = \random_bytes(10);
        $query->redirect_uri = \random_bytes(10);
        $query->state = \random_bytes(10);
        $query->scope = \random_bytes(10);
        foreach ($query as $key => $value) {
            $this->request->query->set($key, $value);
        }

        $formData = new Authorize(
            true,
            (array) $query
        );

        $this->form
            ->expects($this->once())
            ->method('setData')
            ->with($formData)
            ->willReturn($this->form)
        ;

        $this->form
            ->expects($this->once())
            ->method('handleRequest')
            ->with($this->request)
            ->willReturn($this->form)
        ;

        $this->form
            ->expects($this->once())
            ->method('isSubmitted')
            ->with()
            ->willReturn(true)
        ;

        $this->form
            ->expects($this->once())
            ->method('isValid')
            ->with()
            ->willReturn(true)
        ;

        $this->form
            ->expects($this->exactly(6))
            ->method('getData')
            ->with()
            ->willReturn($formData)
        ;

        $this->assertSame([], $_GET);

        $expectedSuperGlobalValue = [
            'client_id' => $query->client_id,
            'response_type' => $query->response_type,
            'redirect_uri' => $query->redirect_uri,
            'state' => $query->state,
            'scope' => $query->scope,
            'nonce' => null,
        ];

        $this->assertTrue($this->instance->process());

        $this->assertSame($expectedSuperGlobalValue, $_GET);
    }

    /**
     * Test that onSuccess handles missing optional OAuth2 parameters (state, scope).
     * Before the fix, this would throw: "Typed property must not be accessed before initialization"
     * because properties were typed as non-nullable string without default values.
     *
     * According to OAuth2 spec, 'state' and 'scope' are optional parameters.
     */
    public function testOnSuccessWithMissingOptionalParameters(): void
    {
        $method = $this->getReflectionMethod('onSuccess');

        // Create Authorize with only required OAuth2 parameters (state and scope omitted)
        $formData = new Authorize(
            true,
            [
                'client_id' => 'test_client_id',
                'response_type' => 'code',
                'redirect_uri' => 'https://example.com/callback',
                // 'state' and 'scope' intentionally omitted - they are optional
            ]
        );

        $this->form
            ->expects($this->exactly(6))
            ->method('getData')
            ->willReturn($formData)
        ;

        $_GET = [];

        // This should not throw an error - nullable properties with defaults should handle this
        $method->invoke($this->instance);

        // Verify that $_GET is populated correctly with null for missing optional parameters
        $this->assertSame([
            'client_id' => 'test_client_id',
            'response_type' => 'code',
            'redirect_uri' => 'https://example.com/callback',
            'state' => null,
            'scope' => null,
            'nonce' => null,
        ], $_GET);
    }

    /**
     * Test process() with missing optional parameters in a real-world scenario.
     * This simulates a POST request where state parameter is not provided.
     */
    public function testProcessWithMissingStateParameter(): void
    {
        $this->request->request->set('accepted', true);
        $this->request->setMethod('POST');

        // Only set required OAuth2 parameters, omit 'state'
        $this->request->query->set('client_id', 'test_client');
        $this->request->query->set('response_type', 'code');
        $this->request->query->set('redirect_uri', 'https://example.com/callback');
        $this->request->query->set('scope', 'read write');
        // 'state' is intentionally not set - it's optional

        $formData = new Authorize(
            true,
            [
                'client_id' => 'test_client',
                'response_type' => 'code',
                'redirect_uri' => 'https://example.com/callback',
                'scope' => 'read write',
                // 'state' not in array
            ]
        );

        $this->form
            ->expects($this->once())
            ->method('setData')
            ->with($formData)
            ->willReturn($this->form)
        ;

        $this->form
            ->expects($this->once())
            ->method('handleRequest')
            ->with($this->request)
            ->willReturn($this->form)
        ;

        $this->form
            ->expects($this->once())
            ->method('isSubmitted')
            ->willReturn(true)
        ;

        $this->form
            ->expects($this->once())
            ->method('isValid')
            ->willReturn(true)
        ;

        $this->form
            ->expects($this->exactly(6))
            ->method('getData')
            ->willReturn($formData)
        ;

        $_GET = [];

        // Before the fix, this would throw an Error when onSuccess() tries to access $state
        $result = $this->instance->process();

        $this->assertTrue($result);

        // Verify $_GET contains null for missing state
        $this->assertSame([
            'client_id' => 'test_client',
            'response_type' => 'code',
            'redirect_uri' => 'https://example.com/callback',
            'state' => null,
            'scope' => 'read write',
            'nonce' => null,
        ], $_GET);
    }

    /**
     * @param string $methodName
     *
     * @return \ReflectionMethod
     */
    protected function getReflectionMethod($methodName)
    {
        $reflectionObject = new \ReflectionObject($this->instance);
        $reflectionMethod = $reflectionObject->getMethod($methodName);

        return $reflectionMethod;
    }
}