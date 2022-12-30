<?php

/**
 * @file
 * Builds placeholder replacement tokens for group node-related data.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\Entity\Node;

/**
 * Implements hook_token_info_alter().
 */
function gnode_token_info_alter(&$info) {
  $info['tokens']['group']['url']['type'] = 'url';
}

/**
 * Implements hook_token_info().
 */
function gnode_token_info() {
  $tokens = [];

  $tokens['group'] = [
    'name' => t('Group'),
    'description' => t('The parent group.'),
    'type' => 'group',
  ];

  if (\Drupal::moduleHandler()->moduleExists('token')) {
    $tokens['groups'] = [
      'name' => t('Groups'),
      'description' => t("An array of all the node parent groups."),
      'type' => 'array',
    ];
  }

  return ['tokens' => ['node' => $tokens]];
}

/**
 * Implements hook_tokens().
 */
function gnode_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  if ($type != 'node' || empty($data['node'])) {
    return [];
  }

  $token_service = \Drupal::token();
  $replacements = [];

  if (is_numeric($data['node'])) {
    $data['node'] = Node::load($data['node']);
  }

  if (!$data['node']->id()) {
    return [];
  }

  $groups = [];
  $group_content_array = GroupContent::loadByEntity($data['node']);
  if (empty($group_content_array)) {
    // Attempt to find group from group route context.
    if ($context = \Drupal::service('group.group_route_context')->getRuntimeContexts(['group'])['group']) {
      $group = $context->getContextValue();
      if (!($group instanceof GroupInterface)) {
        return [];
      }
      $groups[$group->id()] = $group->label();
      $bubbleable_metadata->addCacheableDependency($group);
    }
    else {
      return [];
    }
  }

  /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
  foreach ($group_content_array as $group_content) {
    $group = $group_content->getGroup();
    $groups[$group->id()] = $group->label();
    $bubbleable_metadata->addCacheableDependency($group);
  }

  if (isset($tokens['groups'])) {
    $replacements[$tokens['groups']] = token_render_array($groups, $options);
  }

  // [node:groups:*] chained tokens.
  if ($parents_tokens = \Drupal::token()->findWithPrefix($tokens, 'groups')) {
    $replacements += \Drupal::token()->generate('array', $parents_tokens, ['array' => $groups], $options, $bubbleable_metadata);
  }

  if (!empty($group_content_array)) {
    /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
    $group_content = array_pop($group_content_array);
    $group = $group_content->getGroup();
  }
  if (isset($tokens['group'])) {
    $replacements[$tokens['group']] = $group->label();
  }

  $langcode = $data['node']->language()->getId();
  if ($group->hasTranslation($langcode)) {
    $group = $group->getTranslation($langcode);
  }

  if ($group_tokens = $token_service->findWithPrefix($tokens, 'group')) {
    $replacements += $token_service->generate('group', $group_tokens, ['group' => $group], $options, $bubbleable_metadata);
  }

  return $replacements;
}
