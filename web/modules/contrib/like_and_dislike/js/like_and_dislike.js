/**
 * @file
 * Like and dislike icons behavior.
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.likeAndDislike = {
    attach: function(context, settings) {
      $('.vote-widget--like-and-dislike').once('like-and-dislike').each(function () {
        var $widget = $(this);
        $widget.find('.vote-like a').click(function() {
          var entity_id, entity_type;
          if (!$(this).hasClass('disable-status')) {
            entity_id = $(this).data('entity-id');
            entity_type = $(this).data('entity-type');
            likeAndDislikeService.vote(entity_id, entity_type, 'like');
          }
        });
        $widget.find('.vote-dislike a').click(function() {
          var entity_id, entity_type;
          if (!$(this).hasClass('disable-status')) {
            entity_id = $(this).data('entity-id');
            entity_type = $(this).data('entity-type');
            likeAndDislikeService.vote(entity_id, entity_type, 'dislike');
          }
        });
      });
    }
  };

})(jQuery, Drupal);
