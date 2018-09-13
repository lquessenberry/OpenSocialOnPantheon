<?php

namespace Drupal\swiftmailer\Plugin\Mail;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Site\Settings;
use Drupal\key\KeyInterface;
use Drupal\swiftmailer\Utility\Conversion;
use Exception;
use Html2Text\Html2Text;
use Psr\Log\LoggerInterface;
use stdClass;
use Swift_Attachment;
use Swift_FileSpool;
use Swift_Image;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_Message;
use Swift_NullTransport;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
use Swift_SpoolTransport;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Swift Mailer' plugin to send emails.
 *
 * @Mail(
 *   id = "swiftmailer",
 *   label = @Translation("Swift Mailer"),
 *   description = @Translation("Swift Mailer Plugin.")
 * )
 */
class SwiftMailer implements MailInterface, ContainerFactoryPluginInterface {

  /**
   * @var array
   */
  protected $config;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * SwiftMailer constructor.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $transport
   * @param \Drupal\Core\Config\ImmutableConfig $message
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  public function __construct(ImmutableConfig $transport, ImmutableConfig $message, LoggerInterface $logger, RendererInterface $renderer, ModuleHandlerInterface $module_handler) {
    $this->config['transport'] = $transport->get();
    $this->config['message'] = $message->get();
    $this->logger = $logger;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('config.factory')->get('swiftmailer.transport'),
      $container->get('config.factory')->get('swiftmailer.message'),
      $container->get('logger.factory')->get('swiftmailer'),
      $container->get('renderer'),
      $container->get('module_handler')
    );
  }

  /**
   * Formats a message composed by drupal_mail().
   *
   * @see http://api.drupal.org/api/drupal/includes--mail.inc/interface/MailSystemInterface/7
   *
   * @param array $message
   *   A message array holding all relevant details for the message.
   *
   * @return array
   *   The message as it should be sent.
   */
  public function format(array $message) {
    $message = $this->massageMessageBody($message);

    // Get applicable format.
    $applicable_format = $this->getApplicableFormat($message);

    // Theme message if format is set to be HTML.
    if ($applicable_format == SWIFTMAILER_FORMAT_HTML) {
      $render = [
        '#theme' => isset($message['params']['theme']) ? $message['params']['theme'] : 'swiftmailer',
        '#message' => $message,
      ];

      $message['body'] = $this->renderer->renderPlain($render);

      if ($this->config['message']['convert_mode'] || !empty($message['params']['convert'])) {
        $converter = new Html2Text($message['body']);
        $message['plain'] = $converter->getText();
      }
    }

    // Process any images specified by 'image:' which are to be added later
    // in the process. All we do here is to alter the message so that image
    // paths are replaced with cid's. Each image gets added to the array
    // which keeps track of which images to embed in the e-mail.
    $embeddable_images = [];
    $processed_images = [];
    preg_match_all('/"image:([^"]+)"/', $message['body'], $embeddable_images);
    for ($i = 0; $i < count($embeddable_images[0]); $i++) {
      $image_id = $embeddable_images[0][$i];
      if (isset($processed_images[$image_id])) {
        continue;
      }
      $image_path = trim($embeddable_images[1][$i]);
      $image_name = basename($image_path);

      if (Unicode::substr($image_path, 0, 1) == '/') {
        $image_path = Unicode::substr($image_path, 1);
      }

      $image = new \stdClass();
      $image->uri = $image_path;
      $image->filename = $image_name;
      $image->filemime = \Drupal::service('file.mime_type.guesser')->guess($image_path);
      $image->cid = rand(0, 9999999999);
      $message['params']['images'][] = $image;
      $message['body'] = preg_replace($image_id, 'cid:' . $image->cid, $message['body']);
      $processed_images[$image_id] = 1;
    }

    return $message;
  }

  /**
   * Sends a message composed by drupal_mail().
   *
   * @see http://api.drupal.org/api/drupal/includes--mail.inc/interface/MailSystemInterface/7
   *
   * @param array $message
   *   A message array holding all relevant details for the message.
   *
   * @return bool
   *   TRUE if the message was successfully sent, and otherwise FALSE.
   */
  public function mail(array $message) {
    try {

      // Create a new message.
      $m = Swift_Message::newInstance();

      // Not all Drupal headers should be added to the e-mail message.
      // Some headers must be suppressed in order for Swift Mailer to
      // do its work properly.
      $suppressable_headers = swiftmailer_get_supressable_headers();

      // Keep track of whether we need to respect the provided e-mail
      // format or not.
      $respect_format = $this->config['message']['respect_format'];

      // Process headers provided by Drupal. We want to add all headers which
      // are provided by Drupal to be added to the message. For each header we
      // first have to find out what type of header it is, and then add it to
      // the message as the particular header type.
      if (!empty($message['headers']) && is_array($message['headers'])) {
        foreach ($message['headers'] as $header_key => $header_value) {

          // Check wether the current header key is empty or represents
          // a header that should be suppressed. If yes, then skip header.
          if (empty($header_key) || in_array($header_key, $suppressable_headers)) {
            continue;
          }

          // Skip 'Content-Type' header if the message to be sent will be a
          // multipart message or the provided format is not to be respected.
          if ($header_key == 'Content-Type' && (!$respect_format || swiftmailer_is_multipart($message))) {
            continue;
          }

          // Get header type.
          $header_type = Conversion::swiftmailer_get_headertype($header_key, $header_value);

          // Add the current header to the e-mail message.
          switch ($header_type) {
            case SWIFTMAILER_HEADER_ID:
              Conversion::swiftmailer_add_id_header($m, $header_key, $header_value);
              break;

            case SWIFTMAILER_HEADER_PATH:
              Conversion::swiftmailer_add_path_header($m, $header_key, $header_value);
              break;

            case SWIFTMAILER_HEADER_MAILBOX:
              Conversion::swiftmailer_add_mailbox_header($m, $header_key, $header_value);
              break;

            case SWIFTMAILER_HEADER_DATE:
              Conversion::swiftmailer_add_date_header($m, $header_key, $header_value);
              break;

            case SWIFTMAILER_HEADER_PARAMETERIZED:
              Conversion::swiftmailer_add_parameterized_header($m, $header_key, $header_value);
              break;

            default:
              Conversion::swiftmailer_add_text_header($m, $header_key, $header_value);
              break;

          }
        }
      }

      // Set basic message details.
      Conversion::swiftmailer_remove_header($m, 'From');
      Conversion::swiftmailer_remove_header($m, 'Reply-To');
      Conversion::swiftmailer_remove_header($m, 'To');
      Conversion::swiftmailer_remove_header($m, 'Subject');

      // Parse 'from', 'to' and 'reply-to' mailboxes.
      $from = Conversion::swiftmailer_parse_mailboxes($message['from']);
      $to = Conversion::swiftmailer_parse_mailboxes($message['to']);
      $reply_to = !empty($message['reply-to']) ? Conversion::swiftmailer_parse_mailboxes($message['reply-to']) : $from;

      // Set 'from', 'reply-to', 'to' and 'subject' headers.
      $m->setFrom($from);
      $m->setReplyTo($reply_to);
      $m->setTo($to);
      $m->setSubject($message['subject']);

      // Get applicable format.
      $applicable_format = $this->getApplicableFormat($message);

      // Get applicable character set.
      $applicable_charset = $this->getApplicableCharset($message);

      // Set body.
      $m->setBody($message['body'], $applicable_format, $applicable_charset);

      // Add alternative plain text version if format is HTML and plain text
      // version is available.
      if ($applicable_format == SWIFTMAILER_FORMAT_HTML && !empty($message['plain'])) {
        $m->addPart($message['plain'], SWIFTMAILER_FORMAT_PLAIN, $applicable_charset);
      }

      // Validate that $message['params']['files'] is an array.
      if (empty($message['params']['files']) || !is_array($message['params']['files'])) {
        $message['params']['files'] = [];
      }

      // Let other modules get the chance to add attachable files.
      $files = $this->moduleHandler->invokeAll('swiftmailer_attach', ['key' => $message['key'], 'message' => $message]);
      if (!empty($files) && is_array($files)) {
        $message['params']['files'] = array_merge(array_values($message['params']['files']), array_values($files));
      }

      // Attach files.
      if (!empty($message['params']['files']) && is_array($message['params']['files'])) {
        $this->attach($m, $message['params']['files']);
      }

      // Attach files (provide compatibility with mimemail)
      if (!empty($message['params']['attachments']) && is_array($message['params']['attachments'])) {
        $this->attachAsMimeMail($m, $message['params']['attachments']);
      }

      // Embed images.
      if (!empty($message['params']['images']) && is_array($message['params']['images'])) {
        $this->embed($m, $message['params']['images']);
      }

      // Get the configured transport type.
      $transport_type = $this->config['transport']['transport'];

      // Configure the mailer based on the configured transport type.
      switch ($transport_type) {
        case SWIFTMAILER_TRANSPORT_SMTP:
          // Get transport configuration.
          $host = $this->config['transport']['smtp_host'];
          $port = $this->config['transport']['smtp_port'];
          $encryption = $this->config['transport']['smtp_encryption'];
          $provider =  $this->config['transport']['smtp_credential_provider'];
          $username = NULL;
          $password = NULL;
          if ($provider === 'swiftmailer') {
            $username = $this->config['transport']['smtp_credentials']['swiftmailer']['username'];
            $password = $this->config['transport']['smtp_credentials']['swiftmailer']['password'];
          }
          elseif ($provider === 'key') {
            /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
            $storage = \Drupal::entityTypeManager()->getStorage('key');
            /** @var \Drupal\key\KeyInterface $username_key */
            $username_key = $storage->load($this->config['transport']['smtp_credentials']['key']['username']);
            if ($username_key) {
              $username = $username_key->getKeyValue();
            }
            /** @var \Drupal\key\KeyInterface $password_key */
            $password_key = $storage->load($this->config['transport']['smtp_credentials']['key']['password']);
            if ($password_key) {
              $password = $password_key->getKeyValue();
            }
          }
          elseif ($provider == 'multikey') {
            /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
            $storage = \Drupal::entityTypeManager()->getStorage('key');
            /** @var \Drupal\key\KeyInterface $username_key */
            $user_password_key = $storage->load($this->config['transport']['smtp_credentials']['multikey']['user_password']);
            if ($user_password_key) {
              $values = $user_password_key->getKeyValues();
              $username = $values['username'];
              $password = $values['password'];
            }
          }

          // Instantiate transport.
          $transport = Swift_SmtpTransport::newInstance($host, $port);
          $transport->setLocalDomain('[127.0.0.1]');

          // Set encryption (if any).
          if (!empty($encryption)) {
            $transport->setEncryption($encryption);
          }

          // Set username (if any).
          if (!empty($username)) {
            $transport->setUsername($username);
          }

          // Set password (if any).
          if (!empty($password)) {
            $transport->setPassword($password);
          }
          break;

        case SWIFTMAILER_TRANSPORT_SENDMAIL:
          // Get transport configuration.
          $path = $this->config['transport']['sendmail_path'];
          $mode = $this->config['transport']['sendmail_mode'];

          // Instantiate transport.
          $transport = Swift_SendmailTransport::newInstance($path . ' -' . $mode);
          break;

        case SWIFTMAILER_TRANSPORT_NATIVE:
          // Instantiate transport.
          $transport = Swift_MailTransport::newInstance();
          break;

        case SWIFTMAILER_TRANSPORT_SPOOL:
          // Instantiate transport.
          $spooldir = $this->config['transport']['spool_directory'];
          $spool = new Swift_FileSpool($spooldir);
          $transport = Swift_SpoolTransport::newInstance($spool);
          break;

        case SWIFTMAILER_TRANSPORT_NULL:
          $transport = Swift_NullTransport::newInstance();
          break;
      }

      if (!isset($transport)) {
        throw new \LogicException('The transport method is undefined.');
      }

      $mailer = Swift_Mailer::newInstance($transport);

      // Allows other modules to customize the message.
      $this->moduleHandler->alter('swiftmailer', $mailer, $m, $message);

      // Send the message.
      Conversion::swiftmailer_filter_message($m);
      /** @var Swift_Mailer $mailer */
      return (bool) $mailer->send($m);
    }
    catch (Exception $e) {
      $headers = !empty($m) ? $m->getHeaders() : '';
      $headers = !empty($headers) ? nl2br($headers->toString()) : 'No headers were found.';
      $this->logger->error(
        'An attempt to send an e-mail message failed, and the following error
        message was returned : @exception_message<br /><br />The e-mail carried
        the following headers:<br /><br />@headers',
        ['@exception_message' => $e->getMessage(), '@headers' => $headers]);
      drupal_set_message(t('An attempt to send an e-mail message failed.'), 'error');
    }
    return FALSE;
  }

  /**
   * Process attachments.
   *
   * @param \Swift_Message $m
   *   The message which attachments are to be added to.
   * @param array $files
   *   The files which are to be added as attachments to the provided message.
   */
  private function attach(Swift_Message $m, array $files) {

    // Iterate through each array element.
    foreach ($files as $file) {

      if ($file instanceof stdClass) {

        // Validate required fields.
        if (empty($file->uri) || empty($file->filename) || empty($file->filemime)) {
          continue;
        }

        // Get file data.
        if (UrlHelper::isValid($file->uri, TRUE)) {
          $content = file_get_contents($file->uri);
        }
        else {
          $content = file_get_contents(\Drupal::service('file_system')->realpath($file->uri));
        }

        $filename = $file->filename;
        $filemime = $file->filemime;

        // Attach file.
        $m->attach(Swift_Attachment::newInstance($content, $filename, $filemime));
      }
    }

  }

  /**
   * Process MimeMail attachments.
   *
   * @param \Swift_Message $m
   *   The message which attachments are to be added to.
   * @param array $attachments
   *   The attachments which are to be added message.
   */
  private function attachAsMimeMail(Swift_Message $m, array $attachments) {
    // Iterate through each array element.
    foreach ($attachments as $a) {
      if (is_array($a)) {
        // Validate that we've got either 'filepath' or 'filecontent.
        if (empty($a['filepath']) && empty($a['filecontent'])) {
          continue;
        }

        // Validate required fields.
        if (empty($a['filename']) || empty($a['filemime'])) {
          continue;
        }

        // Attach file (either using a static file or provided content).
        if (!empty($a['filepath'])) {
          $file = new stdClass();
          $file->uri = $a['filepath'];
          $file->filename = $a['filename'];
          $file->filemime = $a['filemime'];
          $this->attach($m, [$file]);
        }
        else {
          $m->attach(Swift_Attachment::newInstance($a['filecontent'], $a['filename'], $a['filemime']));
        }
      }
    }
  }

  /**
   * Process inline images..
   *
   * @param \Swift_Message $m
   *   The message which inline images are to be added to.
   * @param array $images
   *   The images which are to be added as inline images to the provided
   *   message.
   */
  private function embed(Swift_Message $m, array $images) {

    // Iterate through each array element.
    foreach ($images as $image) {

      if ($image instanceof stdClass) {

        // Validate required fields.
        if (empty($image->uri) || empty($image->filename) || empty($image->filemime) || empty($image->cid)) {
          continue;
        }

        // Keep track of the 'cid' assigned to the embedded image.
        $cid = NULL;

        // Get image data.
        if (UrlHelper::isValid($image->uri, TRUE)) {
          $content = file_get_contents($image->uri);
        }
        else {
          $content = file_get_contents(\Drupal::service('file_system')->realpath($image->uri));
        }

        $filename = $image->filename;
        $filemime = $image->filemime;

        // Embed image.
        $cid = $m->embed(Swift_Image::newInstance($content, $filename, $filemime));

        // The provided 'cid' needs to be replaced with the 'cid' returned
        // by the Swift Mailer library.
        $body = $m->getBody();
        $body = preg_replace('/cid:' . $image->cid . '/', $cid, $body);
        $m->setBody($body);
      }
    }
  }

  /**
   * Returns the applicable format.
   *
   * @param array $message
   *   The message for which the applicable format is to be determined.
   *
   * @return string
   *   A string being the applicable format.
   */
  private function getApplicableFormat($message) {
    // Get the configured default format.
    $default_format = $this->config['message']['format'];

    // Get whether the provided format is to be respected.
    $respect_format = $this->config['message']['respect_format'];

    // Check if a format has been provided particularly for this message. If
    // that is the case, then apply that format instead of the default format.
    $applicable_format = !empty($message['params']['format']) ? $message['params']['format'] : $default_format;

    // Check if the provided format is to be respected, and if a format has been
    // set through the header "Content-Type". If that is the case, the apply the
    // format provided. This will override any format which may have been set
    // through $message['params']['format'].
    if ($respect_format && !empty($message['headers']['Content-Type'])) {
      $format = $message['headers']['Content-Type'];

      if (preg_match('/.*\;/U', $format, $matches)) {
        $applicable_format = trim(substr($matches[0], 0, -1));
      }
      else {
        $applicable_format = $message['headers']['Content-Type'];
      }

    }

    return $applicable_format;

  }

  /**
   * Returns the applicable charset.
   *
   * @param array $message
   *   The message for which the applicable charset is to be determined.
   *
   * @return string
   *   A string being the applicable charset.
   */
  private function getApplicableCharset($message) {

    // Get the configured default format.
    $default_charset = $this->config['message']['character_set'];

    // Get whether the provided format is to be respected.
    $respect_charset = $this->config['message']['respect_format'];

    // Check if a format has been provided particularly for this message. If
    // that is the case, then apply that format instead of the default format.
    $applicable_charset = !empty($message['params']['charset']) ? $message['params']['charset'] : $default_charset;

    // Check if the provided format is to be respected, and if a format has been
    // set through the header "Content-Type". If that is the case, the apply the
    // format provided. This will override any format which may have been set
    // through $message['params']['format'].
    if ($respect_charset && !empty($message['headers']['Content-Type'])) {
      $format = $message['headers']['Content-Type'];
      $format = preg_match('/charset.*=.*\;/U', $format, $matches);

      if ($format > 0) {
        $applicable_charset = trim(substr($matches[0], 0, -1));
        $applicable_charset = preg_replace('/charset=/', '', $applicable_charset);
      }
      else {
        $applicable_charset = $default_charset;
      }

    }

    return $applicable_charset;

  }

  /**
   * Massages the message body into the format expected for rendering.
   *
   * @param array $message
   *   The message.
   *
   * @return array
   */
  public function massageMessageBody(array $message) {
    // Get default mail line endings and merge all lines in the e-mail body
    // separated by the mail line endings. Keep Markup objects and escape others
    // and then treat the result as safe markup.
    $line_endings = Settings::get('mail_line_endings', PHP_EOL);
    $applicable_format = $this->getApplicableFormat($message);
    $filter_format = isset($this->config['message']['filter_format']) ? $this->config['message']['filter_format'] : filter_fallback_format();
    $message['body'] = Markup::create(implode($line_endings, array_map(function ($body) use ($applicable_format, $filter_format) {
      // If the body contains no html tags but the applicable format is HTML,
      // we can assume newlines will need be converted to <br>.
      if ($applicable_format == SWIFTMAILER_FORMAT_HTML && Unicode::strlen(strip_tags($body)) === Unicode::strlen($body)) {
        // The default fallback format is 'plain_text', which escapes markup,
        // converts new lines to <br> and converts URLs to links.
        $build = [
          '#type' => 'processed_text',
          '#text' => $body,
          '#format' => $filter_format,
        ];
        $body = $this->renderer->renderPlain($build);
      }
      // If $item is not marked safe then it will be escaped.
      return $body instanceof MarkupInterface ? $body : Html::escape($body);
    }, $message['body'])));
    return $message;
  }

}
