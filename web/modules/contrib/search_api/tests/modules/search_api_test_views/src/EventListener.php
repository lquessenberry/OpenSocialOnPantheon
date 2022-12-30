<?php

namespace Drupal\search_api_test_views;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event listener for testing purposes.
 *
 * @see \Drupal\Tests\search_api\Functional\ViewsTest
 */
class EventListener implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.weather' => 'queryTagAlter',
    ];
  }

  /**
   * Reacts to the query TAG alter event.
   */
  public function queryTagAlter(): void {
    $this->messenger->addStatus('Sunshine');
  }

}
