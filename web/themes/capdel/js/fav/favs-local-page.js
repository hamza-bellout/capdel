(function ($, Drupal) {
  Drupal.behaviors.favLocalScripts = {
    attach: function (context, settings) {
      "use strict";
      $(document,context).once('favLocal').each(function () {
        var storage = new LocalStorageBroker();
        storage.loadEventsData();
        $(document).on("reload-favs", function(e) {
          storage.loadEventsData();
        });
      });
    }
  };

  function LocalStorageBroker() {
    this.getLocalStorageCount = function() {
      var localItems = localStorage.getObj('FavItems') || [];
      return localItems.length;
    };

    this.loadEventsData = function() {
      var wrapper = $('.fav-items-wrapper');
      //if selector exists
      if(wrapper.length) {
        var html = '<div class="heading-title mb-5"><h1><span>Mes favoris</span> ('+this.getLocalStorageCount()+')</h1></div>';

        if(this.getLocalStorageCount() > 0) {
          html += '<div class="row article-row">';

          var items = localStorage.getObj('FavItems') || [];
          items.forEach(function (elem) {
            html += '<div class="col-md-4"><article class="info-post">';
            html += elem.content;
            html += '</article></div>';
          });

          html += '</div>';
        }
        else {
          html += '<div class="view-empty">\n' +
                  '            <h2 class="ff-lato">Aucun événement ajouté pour le moment</h2>\n' +
                  '<p>Organisez facilement tous vos événements grâce à vos favoris. Vous pouvez y accéder à n\'importe quel moment ! \n' +
                  'Il vous suffit d\'ajouter un événement à cette liste grâce à l\'icône<i class="icon-heart-o align-text-bottom ml-2 mr-2"></i>présente sur chaque événement.</p>\n' +
                  '        </div>'
        }

        wrapper.html(html);
        //reload view
        $(document).trigger("re-init-favs");
      }
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