/**
 * Created by mirek on 01.06.18.
 */

(function ($, Drupal) {
  Drupal.behaviors.customSliderScripts = {
    attach: function (context, settings) {
      "use strict";
      var sliderAjaxInProgress = {};

      function checkIfGetNewPage($slider) {
        var currentSlide = $slider.slick('slickCurrentSlide'),
            slidesToShow = $slider.slick('slickGetOption', 'slidesToShow'),
            totalSlides = $slider.slick("getSlick").$slides.length;

        var slidesLeft = totalSlides - (currentSlide + slidesToShow);

        return slidesLeft < slidesToShow;
      }

      jQuery('.slick-slider').on('afterChange', function(){
          var $slider = jQuery(this);

          if(checkIfGetNewPage($slider)){
            var $viewContainer = $slider.closest('.info-row'),
                viewId = $viewContainer.find('input.view_dom_id'),
                viewInstance = Drupal.views.instances['views_dom_id:'+viewId.val()];

            if (typeof viewInstance !== 'undefined' &&
                typeof viewInstance.pagerAjax !== 'undefined') {

              var pager = $viewContainer.find('.js-pager__items'),
                  url = viewInstance.pagerAjax.url,
                  data = viewInstance.pagerAjax.submit,
                  nextPage = data.page;

              //if end not reached
              if(nextPage != '0' && !sliderAjaxInProgress.viewId){

                sliderAjaxInProgress.viewId = true;

                jQuery.ajax({
                  type: 'post',
                  data: data,
                  url: url,
                  success: function( response ) {
                    jQuery.each(response, function(id, el){
                      if(el.method == 'replaceWith' && el.selector == viewInstance.element_settings.selector){
                        var $response = jQuery('<div>'+el.data+'</div>');
                      }
                    });

                    //update pager
                    var $newPager = $response.find('.js-pager__items');
                    pager.html($newPager.html());
                    //remember to update Drupal variables after replacing pager
                    viewInstance.attachPagerAjax();

                    //add new events
                    var $newEvents = $response.find('.slide');
                    $slider.slick('slickAdd', $newEvents);

                    sliderAjaxInProgress.viewId = false;
                  }
                });
              }
            }
          }
        }
      );

      jQuery('.datepicker').datepicker({
        format: "dd/mm/yyyy",
        language: "fr"
      });
    }
  }
})(jQuery, Drupal);
