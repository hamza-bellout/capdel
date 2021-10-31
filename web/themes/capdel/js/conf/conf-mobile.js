(function ($, Drupal) {
  Drupal.behaviors.configurateurScriptsMobile = {
    attach: function (context, settings) {
      "use strict";
      // wrap in document to execute once
      $(document, context).once('confPageMobileScripts').each(function () {
        $(document).ready(function () {

          // STICKY FILTER BUTTONS ON MOBILE
          if ($('#mobile-bar').length && $('#mobile-bar').hasClass('fixed')) {
            $(window).scroll(function () {
              var scroll_top = $(this).scrollTop();
              if (scroll_top > $('#header').height()) {
                $('#mobile-bar').addClass('fixed-top');
              }
              else {
                $('#mobile-bar').removeClass('fixed-top');
              }
            });
          }

          // SHOW FILTERS POPUP
          $('#search-mobile, #filters-mobile').on('click', function (e) {
            if ($('#' + $(e.currentTarget).data('id')).hasClass('modal-block')) {
              $('#' + $(e.currentTarget).data('id')).addClass('show-modal')
            }
            else {
              $('#' + $(e.currentTarget).data('id') + ' .modal-block').addClass('show-modal')
            }
            $('body').addClass('modal-open');

            // CLOSE FILTERS
            $('.close-modal').on('click', function () {
              $('body').removeClass('modal-open');
              $('.modal-block').removeClass('show-modal')
            });
          });

          //filter declaration need to be placed into the timeout block
          //as the facets are loaded after the page loads
          setTimeout(function() {
            // set proper filter url to show
            var blockID = false;
            // condition filters on mobile
            if ($('#block-tbcatfac').length) {
              blockID = 'block-tbcatfac';
            }
            else if ($('#block-lieu-cat-fac').length) {
              blockID = 'block-lieu-cat-fac';
            }
            else if ($('#block-animcatfac').length) {
              blockID = 'block-animcatfac';
            }

            if (blockID) {
              $('button#filters-mobile').data('id', blockID);
              $('.filters-mobile-col').removeClass('hidden');
            }
          },400);
        });
      });
    }
  };
})(jQuery, Drupal);