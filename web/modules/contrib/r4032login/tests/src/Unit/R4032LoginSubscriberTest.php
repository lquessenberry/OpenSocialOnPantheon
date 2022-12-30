<?php

namespace Drupal\Tests\r4032login\Unit {

  use Drupal\Core\DependencyInjection\ContainerBuilder;
  use Drupal\Core\Url;
  use Drupal\r4032login\EventSubscriber\R4032LoginSubscriber;
  use Drupal\Tests\UnitTestCase;
  use Symfony\Component\EventDispatcher\EventDispatcher;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\HttpKernel\Event\ExceptionEvent;
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
     * The messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

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
     * The mocked redirect destination service.
     *
     * @var \Drupal\Core\Routing\RedirectDestinationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $redirectDestination;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void {
      $this->kernel = $this->createMock('Symfony\Component\HttpKernel\HttpKernelInterface');
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

      $this->currentUser = $this->createMock('Drupal\Core\Session\AccountInterface');
      $this->pathMatcher = $this->createMock('\Drupal\Core\Path\PathMatcherInterface');
      $this->eventDispatcher = $this->createMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');
      $this->messenger = $this->createMock('\Drupal\Core\Messenger\MessengerInterface');
      $this->redirectDestination = $this->createMock('\Drupal\Core\Routing\RedirectDestinationInterface');

      $this->urlAssembler = $this->createMock('Drupal\Core\Utility\UnroutedUrlAssemblerInterface');
      $this->urlAssembler->expects($this->any())
        ->method('assemble')
        ->will($this->returnArgument(0));
      $this->router = $this->createMock('Drupal\Tests\Core\Routing\TestRouterInterface');

      $container = new ContainerBuilder();
      $container->set('path.validator', $this->createMock('Drupal\Core\Path\PathValidatorInterface'));
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
      $r4032login = new R4032LoginSubscriber($this->configFactory, $this->currentUser, $this->pathMatcher, $this->eventDispatcher, $this->messenger, $this->redirectDestination);
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
      $config->get('r4032login.settings')
        ->expects($this->any())
        ->method('getCacheContexts')
        ->willReturn([]);
      $config->get('r4032login.settings')
        ->expects($this->any())
        ->method('getCacheTags')
        ->willReturn([]);

      $this->currentUser->expects($this->any())
        ->method('isAnonymous')
        ->willReturn(TRUE);

      $r4032login = new R4032LoginSubscriber($config, $this->currentUser, $this->pathMatcher, $this->eventDispatcher, $this->messenger, $this->redirectDestination);
      $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, new AccessDeniedHttpException());
      $dispatcher = new EventDispatcher();
      $dispatcher->addListener(KernelEvents::EXCEPTION, [
        $r4032login,
        'on403',
      ]);
      $dispatcher->dispatch($event, KernelEvents::EXCEPTION);

      $response = $event->getResponse();
      $this->assertInstanceOf('\Symfony\Component\HttpFoundation\RedirectResponse', $response);
      $this->assertEquals($config_values['default_redirect_code'], $response->getStatusCode());
      $this->assertEquals(Url::fromUserInput($config_values['user_login_path'])
        ->toString(), $response->getTargetUrl());
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
      $config->get('r4032login.settings')
        ->expects($this->any())
        ->method('getCacheContexts')
        ->willReturn([]);
      $config->get('r4032login.settings')
        ->expects($this->any())
        ->method('getCacheTags')
        ->willReturn([]);

      $this->currentUser->expects($this->any())
        ->method('isAuthenticated')
        ->willReturn(TRUE);

      $r4032login = new R4032LoginSubscriber($config, $this->currentUser, $this->pathMatcher, $this->eventDispatcher, $this->messenger, $this->redirectDestination);
      $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, new AccessDeniedHttpException());
      $dispatcher = new EventDispatcher();
      $dispatcher->addListener(KernelEvents::EXCEPTION, [
        $r4032login,
        'on403',
      ]);
      $dispatcher->dispatch($event, KernelEvents::EXCEPTION);

      $response = $event->getResponse();
      $this->assertInstanceOf('\Symfony\Component\HttpFoundation\RedirectResponse', $response);
      $this->assertEquals($config_values['default_redirect_code'], $response->getStatusCode());
      $this->assertEquals($expected_url, $response->getTargetUrl());
    }

    /**
     * Tests RedirectResponse for authenticated users with 404 redirection.
     *
     * @covers ::on403
     */
    public function testAuthenticatedRedirect404() {
      $expected_url = 'base:admin/content';
      $request = new Request(['destination' => $expected_url]);
      $config_values = [
        'display_denied_message' => TRUE,
        'access_denied_message' => 'Access denied. You must log in to view this page.',
        'access_denied_message_type' => 'error',
        'redirect_authenticated_users_to' => '',
        'throw_authenticated_404' => TRUE,
        'user_login_path' => '/user/login',
        'default_redirect_code' => 302,
        'match_noredirect_pages' => '',
      ];
      $config = $this->getConfigFactoryStub([
        'r4032login.settings' => $config_values,
      ]);
      $config->get('r4032login.settings')
        ->expects($this->any())
        ->method('getCacheContexts')
        ->willReturn([]);
      $config->get('r4032login.settings')
        ->expects($this->any())
        ->method('getCacheTags')
        ->willReturn([]);

      $this->currentUser->expects($this->any())
        ->method('isAuthenticated')
        ->willReturn(TRUE);

      $r4032login = new R4032LoginSubscriber($config, $this->currentUser, $this->pathMatcher, $this->eventDispatcher, $this->messenger, $this->redirectDestination);
      $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, new AccessDeniedHttpException());
      $dispatcher = new EventDispatcher();
      $dispatcher->addListener(KernelEvents::EXCEPTION, [
        $r4032login,
        'on403',
      ]);
      $dispatcher->dispatch($event, KernelEvents::EXCEPTION);

      $response = $event->getResponse();
      $this->assertNull($response);
      $this->assertInstanceOf('\Symfony\Component\HttpKernel\Exception\NotFoundHttpException', $event->getThrowable());
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
          ]),
          [
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
          ],
          'base:admin',
        ],
      ];
    }

  }
}
