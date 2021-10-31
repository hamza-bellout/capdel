(function ($, Drupal) {
  Drupal.behaviors.facetsMobileAjax = {
    attach: function (context, settings) {
      "use strict";

      var SHOW_MORE = 'AFFICHER PLUS';
      var SHOW_LESS = 'AFFICHER MOINS';

      //wrap in document to execute once
      $(document, context).once('facetsMobileAjax').each(function () {
        $(document).ready(function () {
          var helper = new OperationHelper();
          var wrapperList = $('.mobile-ajax-hold');

          //remove existing items from ss, they will be regenerated later
          sessionStorage.setObj('FacetsItems',{});

          if(wrapperList.length > 0) {
            helper.prepareItems(wrapperList);

            $('button.mobile-trigger').on('click', function () {
              var query = helper.removeParamsFromQuery();
              query = helper.addParamsToQuery(query);
              window.location.href = window.location.pathname + query;
            });
          }
        });
      });

      $(document).ready(function () {
        // show-more / less button behaviour on the search filters (facets)
        $('p.show-more-button').unbind().on('click', function (e) {
          var data = e.target.dataset;
          var parent = e.target.parentElement;
          var items = ($(parent).children('ul').children('li'));
          if(data.action === 'more') {
            $.each(items, function (index, e) {
              if($(e).hasClass('mobile-ajax-hold')) {
                $(e).addClass('d-md-none');
                $(e).removeClass('d-none');
              }
              else {
                $(e).addClass('d-md-block');
              }
            });
            $(e.target).html(SHOW_LESS);
            $(e.target).attr('data-action','less');
          }
          else if (data.action === 'less') {
            $.each(items, function (index, e) {
              if(index > 9) {
                if($(e).hasClass('mobile-ajax-hold')) {
                  $(e).removeClass('d-md-none');
                  $(e).addClass('d-none');
                }
                else {
                  $(e).removeClass('d-md-block');
                }
              }
            });
            $(e.target).html(SHOW_MORE);
            $(e.target).attr('data-action','more');
          }

          $(e.target).toggleClass('less');

        });
      })
    }
  };

  function OperationHelper() {
    this.prepareItems  = function (wrapperList) {
      var that = this;
      //foreach facet item list on the second level (clickable)
      $.each(wrapperList, function (i, value) {
        var checked = false;
        var checkbox = $(value).find('input.facets-checkbox');
        if(checkbox.length > 0) {
          $(checkbox[0]).removeClass('facets-checkbox');
          checked = ($(checkbox[0]).is(':checked'));
        }

        var anchor = $(value).find('a');
        if(anchor.length > 0) {
          $.each(anchor, function (i, value) {
            $(value).attr('href','#').addClass('mobile-ajax-anchor');
            if(checked) {
              //add this to the ss selection
              that.toggleValueInSS($(value).attr('data-drupal-facet-item-value'));
            }
          }.bind(this));
        }
      });


      $('.mobile-ajax-anchor').on('click', function (element) {
        this.handleMobileFacet(element.target);
      }.bind(this));
    }.bind(this);

    this.handleMobileFacet = function(element) {
      var dataId = element.dataset.drupalFacetItemValue;
      this.toggleValueInSS(dataId);

    };

    this.toggleValueInSS = function(id) {
      var facetsItemsArray = sessionStorage.getObj('FacetsItems') || {};
      if(typeof facetsItemsArray[id] !== 'undefined') {
        delete facetsItemsArray[id];
      }
      else {
        facetsItemsArray[id] = id;
      }
      sessionStorage.setObj('FacetsItems',facetsItemsArray);
    };

    this.removeParamsFromQuery = function() {
      var query = decodeURIComponent(location.search);
      // match f[0]=lieu_cat_fac:318 & f[0]=lieu_cat_fac:318&
      query = query.replace(/f\[\d+]=[a-zA-Z_]+:\d+&?/gmi,'');
      if(query === "?") {
        return "";
      }
      return query;
    };

    this.addParamsToQuery = function(query) {
      var id = $($('.facets-widget-checkbox ul')[0]).attr('data-drupal-facet-alias');
      if(typeof id !== 'undefined') {
        var facetsItemsArray = sessionStorage.getObj('FacetsItems') || {};
        var additionalQuery = '';
        var index = 0;
        $.each(facetsItemsArray,function (key,value) {
          var tempQuery = '';
          if(index > 0) {
            tempQuery = '&f['+index+']='+id+':'+value;
          }
          else {
            if(query !== "") {
              tempQuery = '&f['+index+']='+id+':'+value;
            }
            else {
              tempQuery = '?f['+index+']='+id+':'+value;
            }
          }
          additionalQuery += tempQuery;
          index++;
        });
        query += encodeURI(additionalQuery);
      }
      return query;
    };

  }
})(jQuery, Drupal);

// add some set/get helpers;
Storage.prototype.setObj = function(key, obj) {
  return this.setItem(key, JSON.stringify(obj))
};

Storage.prototype.getObj = function(key) {
  return JSON.parse(this.getItem(key))
};

