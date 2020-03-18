<?php

namespace Drupal\Tests\r4032login\Unit {

  use Drupal\Core\DependencyInjection\ContainerBuilder;
  use Drupal\Core\Url;
  use Drupal\r4032login\EventSubscriber\R4032LoginSubscriber;
  use Drupal\Tests\UnitTestCase;
  use Symfony\Component\EventDispatcher\EventDispatcher;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
  use Symfony\Component\HttpKernel\Exception;
  use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
  use Symfony\Component\HttpKernel\HttpKernelInterface;
  use Symfony\Component\HttpKernel\KernelEvents;

  /**
   * @coversDefaultClass \Drupal\r4032login\EventSubscriber\R4032LoginSubscriber
   * @group r4032login
   */
  class R4032LoginSubscriberTest extends UnitTestCase {

    /**
     * The mocked HTTP kernel.
     *
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $kernel;

    /**
     * The mocked configuration factory.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $configFactory;

    /**
     * The mocked current user.
     *
     * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $currentUser;

    /**
     * The mocked redirect destination service.
     *
     * @var \Drupal\Core\Routing\RedirectDestinationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $redirectDestination;

    /**
     * The path matcher.
     *
     * @var \Drupal\Core\Path\PathMatcherInterface
     */
    protected $pathMatcher;

    /**
     * An event dispatcher instance to use for map events.
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * The mocked unrouted URL assembler.
     *
     * @var \Drupal\Core\Utility\UnroutedUrlAssemblerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $urlAssembler;

    /**
     * The mocked router.
     *
     * @var \Drupal\Tests\Core\Routing\TestRouterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $router;

    /**
     * {@inheritdoc}
     */
    protected function setUp() {
      $this->kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
      $this->configFactory = $this->getConfigFactoryStub([
        'r4032login.settings' => [
          'display_denied_message' => TRUE,
          'access_denied_message' => 'Access denied. You must log in to view this page.',
          'access_denied_message_type' => 'error',
          'redirect_authenticated_users_to' => '',
          'user_login_path' => '/user/login',
          'default_redirect_code' => 302,
          'match_noredirect_pages' => '',
        ],
      ]);
      $this->currentUser = $this->getMock('Drupal\Core\Session\AccountInterface');
      $this->redirectDestination = $this->getMock('\Drupal\Core\Routing\RedirectDestinationInterface');
      $this->pathMatcher = $this->getMock('\Drupal\Core\Path\PathMatcherInterface');
      $this->eventDispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');

      $this->urlAssembler = $this->getMock('Drupal\Core\Utility\UnroutedUrlAssemblerInterface');
      $this->urlAssembler->expects($this->any())->method('assemble')->will($this->returnArgument(0));
      $this->router = $this->getMock('Drupal\Tests\Core\Routing\TestRouterInterface');

      $container = new ContainerBuilder();
      $container->set('path.validator', $this->getMock('Drupal\Core\Path\PathValidatorInterface'));
      $container->set('router.no_access_checks', $this->router);
      $container->set('unrouted_url_assembler', $this->urlAssembler);
      \Drupal::setContainer($container);
    }

    /**
     * Tests constructor for the R4032LoginSubscriber instance.
     *
     * @covers ::__construct
     */
    public function testConstruct() {
      $r4032login = new R4032LoginSubscriber($this->configFactory, $this->currentUser, $this->redirectDestination, $this->pathMatcher, $this->eventDispatcher);
      $this->assertInstanceOf('\Drupal\r4032login\EventSubscriber\R4032LoginSubscriber', $r4032login);
    }

    /**
     * Tests RedirectResponse for anonymous users.
     *
     * @param Request $request
     *   The request object.
     * @param array $config_values
     *   The configuration values.
     * @param string $expected_url
     *   The expected target URL.
     *
     * @covers ::on403
     *
     * @dataProvider providerRequests
     */
    public function testAnonymousRedirect(Request $request, array $config_values, $expected_url) {
      $config = $this->getConfigFactoryStub([
        'r4032login.settings' => $config_values,
      ]);
      $this->currentUser->expects($this->any())->method('isAnonymous')->willReturn(TRUE);

      $r4032login = new R4032LoginSubscriber($config, $this->currentUser, $this->redirectDestination, $this->pathMatcher, $this->eventDispatcher);
      $event = new GetResponseForExceptionEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, new AccessDeniedHttpException());
      $dispatcher = new EventDispatcher();
      $dispatcher->addListener(KernelEvents::EXCEPTION, [
        $r4032login, 'on403',
      ]);
      $dispatcher->dispatch(KernelEvents::EXCEPTION, $event);

      $response = $event->getResponse();
      $this->assertInstanceOf('\Symfony\Component\HttpFoundation\RedirectResponse', $response);
      $this->assertEquals($config_values['default_redirect_code'], $response->getStatusCode());
      $this->assertEquals(Url::fromUserInput($config_values['user_login_path'])->toString(), $response->getTargetUrl());
    }

    /**
     * Tests RedirectResponse for authenticated users.
     *
     * @param Request $request
     *   The request object.
     * @param array $config_values
     *   The configuration values.
     * @param string $expected_url
     *   The expected target URL.
     *
     * @covers ::on403
     *
     * @dataProvider providerRequests
     */
    public function testAuthenticatedRedirect(Request $request, array $config_values, $expected_url) {
      $config = $this->getConfigFactoryStub([
        'r4032login.settings' => $config_values,
      ]);
      $this->currentUser->expects($this->any())->method('isAuthenticated')->willReturn(TRUE);

      $r4032login = new R4032LoginSubscriber($config, $this->currentUser, $this->redirectDestination, $this->pathMatcher, $this->eventDispatcher);
      $event = new GetResponseForExceptionEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, new AccessDeniedHttpException());
      $dispatcher = new EventDispatcher();
      $dispatcher->addListener(KernelEvents::EXCEPTION, [
        $r4032login, 'on403',
      ]);
      $dispatcher->dispatch(KernelEvents::EXCEPTION, $event);

      $response = $event->getResponse();
      $this->assertInstanceOf('\Symfony\Component\HttpFoundation\RedirectResponse', $response);
      $this->assertEquals($config_values['default_redirect_code'], $response->getStatusCode());
      $this->assertEquals($expected_url, $response->getTargetUrl());
    }

    /**
     * Tests the listener type for event subscriber exception events.
     *
     * @covers ::getSubscribedEvents
     */
    public function testGetSubscribedEvents() {
      $expected = [
        KernelEvents::EXCEPTION => [
          [
            'onException',
            0,
          ],
        ],
      ];
      $actual = R4032LoginSubscriber::getSubscribedEvents();
      $this->assertSame($expected, $actual);
    }

    /**
     * Provides requests, config, and expected paths to ::on403().
     *
     * @return array
     *   An array of Request objects, configuration values, and expected paths.
     */
    public function providerRequests() {
      return [
        [
          new Request([
            'destination' => 'test',
          ]), [
            'display_denied_message' => TRUE,
            'access_denied_message' => 'Access denied. You must log in to view this page.',
            'access_denied_message_type' => 'error',
            'redirect_authenticated_users_to' => '/user/login',
            'user_login_path' => '/user/login',
            'default_redirect_code' => 302,
            'match_noredirect_pages' => '',
          ],
          'base:user/login',
        ],
        [
          new Request([
            'destination' => 'test',
          ]),
          [
            'display_denied_message' => TRUE,
            'access_denied_message' => 'Access denied. You must log in to view this page.',
            'access_denied_message_type' => 'error',
            'redirect_authenticated_users_to' => '/admin',
            'user_login_path' => '/user/login',
            'default_redirect_code' => 302,
            'match_noredirect_pages' => '',
          ], 'base:admin',
        ],
      ];
    }

    /**
     * Provides predefined and SPL exception events to ::on403().
     *
     * @return array
     *   An array of GetResponseForExceptionEvent exception events.
     */
    public function providerInvalidExceptions() {
      $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
      $exceptions = [];
      foreach (get_declared_classes() as $name) {
        $class = new \ReflectionClass($name);
        if ($class->isSubclassOf('Exception')) {
          $exceptions[] = [
            new GetResponseForExceptionEvent($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $this->getMockBuilder($name)->disableOriginalConstructor()->getMock()),
          ];
        }
      }
      return $exceptions;
    }

  }
}

namespace {

  if (!function_exists('drupal_set_message')) {

    /**
     * Replaces drupal_set_message().
     */
    function drupal_set_message() {
    }

  }
}
