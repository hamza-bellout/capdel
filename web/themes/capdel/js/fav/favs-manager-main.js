(function ($, Drupal) {
  Drupal.behaviors.favScripts = {
    attach: function (context, settings) {
      //todo: get favs count from localstorage or preprocess page
      "use strict";

      var storage = new LocalStorageBroker();
      var helper = new DomLocalStorageBroker();

      function refreshView() {
        if(settings.hasOwnProperty('views') && settings.views.hasOwnProperty('ajaxViews')) {
          Object.keys(settings.views.ajaxViews).forEach(function (elem) {
            if(settings.views.ajaxViews[elem].view_display_id == "mes_favoris") {
              var selector = '.js-view-dom-id-' + settings.views.ajaxViews[elem].view_dom_id;
              $(selector).trigger('RefreshView');
            }
          });
        }
      }

      function setBinds() {
        $('.event-fav').unbind().on('click',function (e) {
          e.preventDefault();
          e.stopPropagation();

          if($(e.target).hasClass('anonym')) {
            if($(e.target).hasClass('active')) {
              $('.remove-fav-button').attr('data-nid', $(e.target).attr('data-nid'));
              $('#removeFavourite').modal('show');
            }
            else {
              var eventWrapper = $('.event-fav-box');
              if(eventWrapper.length) {
                storage.addToFavs($(e.target).attr('data-nid'),true);
              }
              else {
                storage.addToFavs($(e.target).attr('data-nid'),false);
              }
              $(e.target).addClass('active');
              storage.updateFavCount('load');
            }
          }
          else {
            //logged in user
            // this is ugly way of : go up, find div rendered by flag module, get it's child and pull the href with token
            var hrefElement = ($($(e.target).parent()).find('a.bookmark-fav')[0]);

            if($(hrefElement).hasClass('active')) {
              $('.remove-fav-button').attr('data-nid',$(e.target).attr('data-nid'));
              $('#removeFavourite').modal('show');

            }
            else {
              $(hrefElement).trigger('click');
              $(e.target).addClass('active');
              refreshView();
              storage.updateFavCount('add');
            }
          }
          helper.handleEventDetailFavLabel();
        }.bind(this));
        $('.remove-fav-button').unbind().on('click',function (e) {
          e.preventDefault();
          e.stopPropagation();

          var favElement = $('.event-fav[data-nid="'+$(e.target)[0].dataset.nid+'"]');

          if($(favElement).hasClass('anonym')) {
            storage.removeFromFavs($(e.target)[0].dataset.nid);
            $(favElement).removeClass('active');
            storage.updateFavCount('load');
          }
          else {
            var hrefElemId = '.js-flag-bookmark-' + $(e.target)[0].dataset.nid;
            if($(hrefElemId)) {
              $(hrefElemId).trigger("click");
              $(favElement).removeClass('active');
              refreshView();
              storage.updateFavCount('remove');
            }
          }
          $('#removeFavourite').modal('hide');
          helper.handleEventDetailFavLabel();
        }.bind(this));
      }

      //setting timeout to wait for BigPipe scripts
      setTimeout(function(){
        $(document,context).once('favScripts').each(function () {
          var helper = new DomLocalStorageBroker();
          helper.initFavs();
          setBinds();
          storage.updateFavCount('load');
          helper.handleEventDetailFavLabel();

          //set binds again when the view is refreshed
          $(document).ajaxStop(function() {
            helper.initFavs();
            setBinds();
            storage.updateFavCount('load');
            helper.handleEventDetailFavLabel();
          });
        });
      }, 500);

      $(document,context).each(function () {
        var helper = new DomLocalStorageBroker();
        $(document).on("re-init-favs", function(e) {
          helper.initFavs();
          setBinds();
          storage.updateFavCount('load');
        });
      });
    }
  };

  function DomLocalStorageBroker() {
    var storage = new LocalStorageBroker();
    this.initFavs = function () {
      var favs = $('.fav-wrapper');
      favs.each(function (index,elem) {
        var anchor = (($(elem).find('a.bookmark-fav')[0]));
        //if we found anchor - then logged in user or localstorage anonym user
        if((typeof anchor !== 'undefined' && $(anchor).hasClass('active')) ||
           (typeof anchor === 'undefined' && (storage.checkFav($(elem).find('.event-fav')[0].dataset.nid) !== false))) {
            $($(elem).find('.event-fav')[0]).addClass('active');
        }
      })
    };

    this.handleEventDetailFavLabel = function () {
      var activeText = 'Retirer de mes favoris <i class="icon-heart align-text-bottom ml-3"></i>';
      var inactiveText = 'Ajouter à mes favoris <i class="icon-heart-o align-text-bottom ml-3"></i>';
      var eventWrapper = $('.event-fav-box');
      //if found I am in event detail page
      if(eventWrapper.length) {
        var favPlaceholder = $(eventWrapper).find('.event-fav')[0];
        //I found proper placeholder
        if(favPlaceholder) {
          //handle anonym user
          if ($(favPlaceholder).hasClass('anonym')) {
            var inLocal = storage.checkFav($(favPlaceholder)[0].dataset.nid);
            if(inLocal !== false) {
              $(favPlaceholder).html(activeText);
              $(favPlaceholder).addClass('active');
            }
            else {
              $(favPlaceholder).html(inactiveText);
              $(favPlaceholder).removeClass('active');
            }
          }
          //handle logged in user
          else {
            var hrefElement = $(eventWrapper).find('a.bookmark-fav')[0];
            if($(hrefElement).hasClass('active')) {
              $(favPlaceholder).html(activeText);
              $(favPlaceholder).addClass('active');
            }
            else {
              $(favPlaceholder).html(inactiveText);
              $(favPlaceholder).removeClass('active');
            }
          }
        }
      }
    }
  }

  function LocalStorageBroker() {
    this.addToFavs = function (nid,isEventDetailPage) {
      var localItems = localStorage.getObj('FavItems') || [];
      var elem = this.checkFav(nid);
      var nodeContent;
      if(isEventDetailPage  !== true) {
        nodeContent = $($('.event-fav[data-nid="'+nid+'"]')[0].parentElement.parentElement).html();
      }
      else {
        //we need to build nodeContent structure;
        nodeContent = this.buildTheNodeStructure(nid);
      }

      if(nodeContent) {
        var nodeElem = {};
        nodeElem.nid = nid;
        nodeElem.content = nodeContent;
        if(elem === -1 || elem === false) {
          localItems.push(nodeElem);
        }
        else {
          localItems[elem] = nodeElem;
        }
        localStorage.setObj('FavItems',localItems);
        $(document).trigger("reload-favs");
      }
    };

    this.removeFromFavs = function (nid) {
      var localItems = localStorage.getObj('FavItems') || [];
      localItems = $.grep(localItems, function (e) {
        return e.nid != nid;
      });
      localStorage.setObj('FavItems',localItems);
      $(document).trigger("reload-favs");
    };

    this.checkFav = function (nid) {
      var localItems = localStorage.getObj('FavItems');
      if(localItems == null) {
        return false;
      }
      else {
        var elemIndex = localItems.findIndex(function (obj) {
          if (obj.nid === nid) {
            return obj;
          }
        });

        if(elemIndex !== -1) {
          return elemIndex;
        }
      }
      return false;
    };


    this.updateFavCount = function (action) {
      var counterAnonym = $('.fav-counter-badge-anonym');
      var counterRegistered = $('.fav-counter-badge');

      switch (action) {
        case 'load':
          var count = this.getLocalStorageCount();
          if(count > 0) {
            counterAnonym.html(this.getLocalStorageCount());
            $(counterAnonym).removeClass('hidden');
          }
          else {
            $(counterAnonym).addClass('hidden');
          }
          break;
        case 'add':
          counterRegistered.html(parseInt(counterRegistered.html()) + 1);
          if (($(counterRegistered).hasClass('hidden'))) {
            $(counterRegistered).removeClass('hidden');
          }
          break;
        case 'remove':
          counterRegistered.html(parseInt(counterRegistered.html()) - 1);
          if(parseInt(counterRegistered.html()) <= 0) {
            $(counterRegistered).addClass('hidden');
            counterRegistered.html(0);
          }
          break;
      }
    };

    this.getLocalStorageCount = function() {
      var localItems = localStorage.getObj('FavItems') || [];
      return localItems.length;
    };

    this.buildTheNodeStructure = function(nid) {
      var url,
          pictureName,
          pictureAlt,
          location,
          category,
          title,
          description;

      url = window.location.pathname;

      pictureName = $('#carouselExampleFade .carousel-inner')
        .children()[0]
        .getAttribute('style')
        .split('url(')[1]
        .split('?')[0];

      location = $($('ul.details').children()[0]).html().split('</i>')[1];

      category = $($('.description div').children()[0]).html();

      title = pictureAlt =  $('.title h1').html();

      description = $.trim($('.description-text').html()).substring(0, 200)
                      .split(" ").slice(0, -1).join(" ") + "...";


      return          '        <div class="fav-wrapper">\n' +
                      '                  <a href="#" class="event-fav link-fav anonym active" data-nid="'+nid+'" tabindex="0"></a>\n' +
                      '              </div>\n' +
                      '    <a class="info-wrap" href="'+url+'" tabindex="0">\n' +
                      '        <figure class="img-holder">\n' +
                      '              <div class="img-wrap"><img src="'+pictureName+'" alt="'+pictureAlt+'"></div>\n' +
                      '            <figcaption class="row caption">\n' +
                      '                <div class="col-6"><div class="info-location">'+location+'</div></div>\n' +
                      '        \n' +
                      '              </figcaption>\n' +
                      '    </figure>\n' +
                      '    <div class="description">\n' +
                      '      <span class="category">'+category+'</span>\n' +
                      '      <h3>'+title+'</h3>\n' +
                      '      <p>'+description+'</p>\n' +
                      '    </div>\n' +
                      '  </a>';
    }
  }

})(jQuery, Drupal);

// add some set/get helpers;
Storage.prototype.setObj = function(key, obj) {
  return this.setItem(key, JSON.stringify(obj))
};

Storage.prototype.getObj = function(key) {
  return JSON.parse(this.getItem(key))
};
