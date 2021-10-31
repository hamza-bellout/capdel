(function ($, Drupal) {
  Drupal.behaviors.reservationPageScripts = {
    attach: function (context, settings) {
      "use strict";

      //wrap in document to execute once
      $(document,context).once('reservationPage').each(function () {

        $(document).ready(function (){
          setTimeout(function(

          ){
            var headerContent = "<div class=\"heading-title mb-5 mt-5\"><h1><span>Mes demandes</span> (0)</h1></div><div class=\"information mb-5\">Aucun événement ajouté pour le moment.</div>"
            var current = $(".views_block\\:_reservations_current-block_1").length;
            var old = $(".views_block\\:_reservations_old-block_1").length;
            var canceled = $(".views_block\\:_reservations_canceled-block_1").length;

            if(!(current || old || canceled)) {
              $('#reservations .header').html(headerContent);
            }

          },1000);

        });
      });
    }
  };
})(jQuery, Drupal);

