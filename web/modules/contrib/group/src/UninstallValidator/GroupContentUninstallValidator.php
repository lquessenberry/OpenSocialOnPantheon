<?php

namespace Drupal\group\UninstallValidator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;

class GroupContentUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group content plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * Constructs a new GroupContentUninstallValidator object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager
   *   The group content plugin manager.
   */
  public function __construct(TranslationInterface $string_translation, EntityTypeManagerInterface $entity_type_manager, GroupContentEnablerManagerInterface $plugin_manager) {
    $this->stringTranslation = $string_translation;
    $this->entityTypeManager = $entity_type_manager;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = $plugin_names = [];

    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
    foreach ($this->pluginManager->getAll() as $plugin_id => $plugin) {
      if ($plugin->getProvider() == $module && $this->hasGroupContent($plugin_id)) {
        $plugin_names[] = $plugin->getLabel();
      }
    }

    if (!empty($plugin_names)) {
      $reasons[] = $this->t('The following group content plugins still have content for them: %plugins.', ['%plugins' => implode(', ', $plugin_names)]);
    }

    return $reasons;
  }

  /**
   * Determines if there is any group content for a content enabler plugin.
   *
   * @param string $plugin_id
   *   The group content enabler plugin ID to check for group content.
   *
   * @return bool
   *   Whether there are group content entities for the given plugin ID.
   */
  protected function hasGroupContent($plugin_id) {
    $group_content_types = array_keys(GroupContentType::loadByContentPluginId($plugin_id));

    if (empty($group_content_types)) {
      return FALSE;
    }

    $entity_count = $this->entityTypeManager->getStorage('group_content')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $group_content_types, 'IN')
      ->count()
      ->execute();

    return (bool) $entity_count;
  }

}
