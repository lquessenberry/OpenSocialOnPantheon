<?php

namespace Drupal\ginvite\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\ginvite\GroupInvitationLoader;
use Drupal\ginvite\Event\UserRegisteredFromInvitationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ginvite module event subscriber.
 *
 * @package Drupal\ginvite\EventSubscriber
 */
class GinviteSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Group invitations loader.
   *
   * @var \Drupal\ginvite\GroupInvitationLoader
   */
  protected $groupInvitationLoader;

  /**
   * The current user's account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs GinviteSubscriber.
   *
   * @param \Drupal\ginvite\GroupInvitationLoader $invitation_loader
   *   Invitations loader service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function __construct(GroupInvitationLoader $invitation_loader, AccountInterface $current_user, MessengerInterface $messenger, LoggerChannelFactoryInterface $logger_factory) {
    $this->groupInvitationLoader = $invitation_loader;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Notify user about Pending invitations.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The GetResponseEvent to process.
   */
  public function notifyAboutPendingInvitations(GetResponseEvent $event) {
    if ($this->groupInvitationLoader->loadByUser()) {
      // Exclude routes where this info is redundant or will generate a
      // misleading extra message on the next request.
      $route_exclusions = [
        'view.my_invitations.page_1',
        'ginvite.invitation.accept',
        'ginvite.invitation.decline',
      ];
      $route = $event->getRequest()->get('_route');

      if (!empty($route) && !in_array($route, $route_exclusions)) {
        $destination = Url::fromRoute('view.my_invitations.page_1', ['user' => $this->currentUser->id()])->toString();
        $replace = ['@url' => $destination];
        $message = $this->t('You have pending group invitations. <a href="@url">Visit your profile</a> to see them.', $replace);
        $this->messenger->addMessage($message, 'warning', FALSE);
      }
    }
  }

  /**
   * Unblock users when they are coming from pending invitations.
   *
   * @param \Drupal\ginvite\Event\UserRegisteredFromInvitationEvent $event
   *   The UserRegisteredFromInvitationEvent to process.
   */
  public function unblockInvitedUsers(UserRegisteredFromInvitationEvent $event) {
    $invitation = $event->getGroupInvitation();
    $plugin_configuration = $invitation->getGroup()->getGroupType()->getContentPlugin('group_invitation')->getConfiguration();
    if ($plugin_configuration['unblock_invitees']) {
      $invited_user = $invitation->getUser();
      $invited_user->activate();
      $invited_user->save();
      $this->messenger->addMessage($this->t("User %user unblocked as it comes from an invitation", ["%user" => $invited_user->getDisplayName()]));
      $this->loggerFactory->get('ginvite')->notice($this->t("User %user unblocked as it comes from an invitation", ["%user" => $invited_user->getDisplayName()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['notifyAboutPendingInvitations'];
    $events['user_registered_from_invitation'][] = ['unblockInvitedUsers'];
    return $events;
  }

}
