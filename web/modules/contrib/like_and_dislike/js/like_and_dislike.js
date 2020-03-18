/**
 * @file
 * Like and dislike icons behavior.
 */
(function ($, Drupal) {

    Drupal.behaviors.likeAndDislike = {
      attach: function(context, settings) {
        $('.vote-like a').unbind('click');
        $('.vote-like a').click(function() {
          var entity_id, entity_type;
          if (!$(this).hasClass('disable-status')) {
            entity_id = $(this).data('entity-id');
            entity_type = $(this).data('entity-type');
            likeAndDislikeService.vote(entity_id, entity_type, 'like');
          }
        });
        $('.vote-dislike a').unbind('click');
        $('.vote-dislike a').click(function() {
          var entity_id, entity_type;
          if (!$(this).hasClass('disable-status')) {
            entity_id = $(this).data('entity-id');
            entity_type = $(this).data('entity-type');
            likeAndDislikeService.vote(entity_id, entity_type, 'dislike');
          }
        });
      }
    };

})(jQuery, Drupal);
