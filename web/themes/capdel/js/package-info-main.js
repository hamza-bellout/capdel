(function ($, Drupal) {
  Drupal.behaviors.packageInfoScripts = {
    attach: function (context, settings) {
      "use strict";

      //wrap in document to execute once
      $(document,context).once('reservationsPage').each(function () {
        $(document,context).on('click', '.info-wrap', function (e) {
          var domHelper = new DomHelper();
          //catch only config pages
          if(e.currentTarget.getAttribute('href') == "#" && e.currentTarget.getAttribute('data-package-id')) {
            domHelper.getPackage(e.currentTarget.getAttribute('data-package-id'));
            e.preventDefault();
          }
        });
      });
    }
  };

  function DomHelper() {

    this.getPackage = function(id) {
      $.get('/rest/session/token')
        .done(function (data) {
          var csrfToken = data;
          $.ajax({
            url: '/api/v1.0/custom/reservation/'+id+'?_format=json',
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Basic YWRtaW46algkdTI7NHBLVg==',
              'X-CSRF-Token': csrfToken
            },
            success: function (response) {
              this.prepareResumeModal(response);
              $('#resumeModal').modal('show');
            }.bind(this),
            error: function (response) {
              $('#bookingModalFail').modal('show');
            }.bind(this)
          });
        }.bind(this));
    };

    this.prepareResumeModal = function(data) {

      var menuItems = data.items.menuItems,
      LieuItems = data.items.lieuItems,
      OptionsItems = data.items.OptionsItems,
      TBItems = data.items.TBItems,
      AnimItems = data.items.AnimItems,
      budgetTotal = 0,
      remainingBudget = data.remainingBudget,
      budgetTotalPerPer = data.budgetTotalPerPer;

      var priceMultiplier = data['selected values'][4].split('=')[1];
      var pricePerPerson = data['selected values'][5].split('=')[1];
      var selectedDate = decodeURIComponent(data['selected values'][2].split('=')[1] || "");

      var overallBudget = priceMultiplier * pricePerPerson;

      var menuTabs = "";
      var lieuTabs = "";
      var optTabs = "";
      var tbTabs = "";
      var animTabs = "";

      LieuItems.forEach(function (element) {

        if (element.category == null) {
          element.category = '';
        }
        if (element.destination == null) {
          element.destination = '';
        }

        var lieuElem = "<div class=\"card mb-3\">\n" +
                       "                                    <div class=\"row\">\n" +
                       "                                        <div class=\"col-8\">\n" +
                       "                                            <span class=\"title\">"+ element.title + "</span>\n" +
                       "                                            <span class=\"category\">"+ element.category+"</span>\n" +
                       "                                        </div>\n" +
                       "                                        <div class=\"col-4\">\n" +
                       "                                                <div class=\"info-location\">"+element.destination+"</div>\n" +
                       "                                        </div>\n" +
                       "                                    </div>\n" +
                       "                                    <div class=\"row align-items-end single\">\n" +
                       "                                        <div class=\"col-8\">\n" +
                       "                                            " +selectedDate +
                       "                                         </div>\n" +
                       "                                        <div class=\"col-4 price\">\n" +
                       "                                        </div>\n" +
                       "\n" +
                       "                                    </div>\n" +
                       "                                </div>";

        lieuTabs = lieuTabs + lieuElem;
      },this);

      menuItems.forEach(function (element) {

        if (element.category == null) {
          element.category = '';
        }

        var priceTag = "";
        if (element.count > 1) {
          priceTag = element.price +"€ x "+ element.count +"  personnes";
        }
        else {
          element.count = 1;
        }

        var menuElem = "<div class=\"card mb-3\">\n" +
                       "                                    <div class=\"row\">\n" +
                       "                                        <div class=\"col-8\">\n" +
                       "                                            <span class=\"title\">"+ element.title + "</span>\n" +
                       "                                            <span class=\"category\">"+ element.category+"</span>\n" +
                       "                                        </div>\n" +
                       "                                        <div class=\"col-4\">\n" +
                       "                                        </div>\n" +
                       "                                    </div>\n" +
                       "                                    <div class=\"row align-items-end single\">\n" +
                       "                                        <div class=\"col-8\">\n" +
                       "                                            " + priceTag +
                       "                                         </div>\n" +
                       "                                        <div class=\"col-4 price\">\n" +
                       "                                            <strong>"+parseInt(element.price)*element.count +" €</strong>\n" +
                       "                                        </div>\n" +
                       "\n" +
                       "                                    </div>\n" +
                       "                                </div>";

        menuTabs = menuTabs + menuElem;
        budgetTotal = budgetTotal + parseInt(parseInt(element.price)*parseInt(element.count));
      },this);

      $('.offer .item[data-tab="place"]').html('');
      if(LieuItems.length > 0) {
        if(menuItems.length > 0) {
          $('.offer .item[data-tab="place"]').html(lieuTabs + menuTabs);
        }
        else {
          $('.offer .item[data-tab="place"]').html(lieuTabs + "Aucune formule Menus & boissons n'a été sélectionnée");
        }
      }
      else {
        if(menuItems.length > 0) {
          $('.offer .item[data-tab="place"]').html("Aucun lieu n'a été sélectionné" + menuTabs);
        }
        else {
          $('.offer .item[data-tab="place"]').html("Aucun lieu n'a été sélectionné <br/>Aucune formule Menus & boissons n'a été sélectionnée ");
        }
      }


      OptionsItems.forEach(function (element) {

        if (element.category == null) {
          element.category = '';
        }

        var priceTag = "";
        if (element.count > 1) {
          priceTag = element.price +"€ x "+ element.count;
        }
        else {
          element.count = 1;
        }

        var optElem = "<div class=\"card mb-3\">\n" +
                      "                                    <div class=\"row\">\n" +
                      "                                        <div class=\"col-8\">\n" +
                      "                                            <span class=\"title\">"+ element.title + "</span>\n" +
                      "                                            <span class=\"category\">"+ element.category+"</span>\n" +
                      "                                        </div>\n" +
                      "                                        <div class=\"col-4\">\n" +
                      "                                        </div>\n" +
                      "                                    </div>\n" +
                      "                                    <div class=\"row align-items-end single\">\n" +
                      "                                        <div class=\"col-8\">\n" +
                      "                                            " + priceTag +
                      "                                         </div>\n" +
                      "                                        <div class=\"col-4 price\">\n" +
                      "                                            <strong>"+parseInt(element.price)*element.count +" €</strong>\n" +
                      "                                        </div>\n" +
                      "\n" +
                      "                                    </div>\n" +
                      "                                </div>";

        optTabs = optTabs + optElem;
        budgetTotal = budgetTotal + parseInt(parseInt(element.price)*parseInt(element.count));
      },this);

      if(OptionsItems.length > 0) {
        $('.offer .item[data-tab="options"]').html('');
        $('.offer .item[data-tab="options"]').html(optTabs);
      }
      else {
        $('.offer .item[data-tab="options"]').html('Aucune option n\'a été sélectionnée');
      }

      TBItems.forEach(function (element) {

        if (element.category == null) {
          element.category = '';
        }
        if (element.destination == null) {
          element.destination = '';
        }

        var tbElem = "<div class=\"card mb-3\">\n" +
                     "                                    <div class=\"row\">\n" +
                     "                                        <div class=\"col-8\">\n" +
                     "                                            <span class=\"title\">"+ element.title + "</span>\n" +
                     "                                            <span class=\"category\">"+ element.category+"</span>\n" +
                     "                                        </div>\n" +
                     "                                        <div class=\"col-4\">\n" +
                     "                                                <div class=\"info-location\">"+element.destination+"</div>\n" +
                     "                                        </div>\n" +
                     "                                    </div>\n" +
                     "                                    <div class=\"row align-items-end single\">\n" +
                     "                                        <div class=\"col-8\">\n" +
                     "                                            " +element.price +"€ x "+ priceMultiplier +" personnes" +
                     "                                         </div>\n" +
                     "                                        <div class=\"col-4 price\">\n" +
                     "                                            <strong>"+parseInt(element.price)*priceMultiplier +" €</strong>\n" +
                     "                                        </div>\n" +
                     "\n" +
                     "                                    </div>\n" +
                     "                                </div>";

        tbTabs = tbTabs + tbElem;
        budgetTotal = budgetTotal + parseInt(parseInt(element.price)*priceMultiplier);
      },this);

      if(TBItems.length > 0) {
        $('.offer .item[data-tab="tb"]').html('');
        $('.offer .item[data-tab="tb"]').html(tbTabs);
      }
      else {
        $('.offer .item[data-tab="tb"]').html('Aucune activité n\'a été sélectionnée');
      }

      AnimItems.forEach(function (element) {

        if (element.category == null) {
          element.category = '';
        }
        if (element.destination == null) {
          element.destination = '';
        }

        var animElem = "<div class=\"card mb-3\">\n" +
                       "                                    <div class=\"row\">\n" +
                       "                                        <div class=\"col-8\">\n" +
                       "                                            <span class=\"title\">"+ element.title + "</span>\n" +
                       "                                            <span class=\"category\">"+ element.category+"</span>\n" +
                       "                                        </div>\n" +
                       "                                        <div class=\"col-4\">\n" +
                       "                                                <div class=\"info-location\">"+element.destination+"</div>\n" +
                       "                                        </div>\n" +
                       "                                    </div>\n" +
                       "                                    <div class=\"row align-items-end single\">\n" +
                       "                                        <div class=\"col-8\">\n" +
                       "                                            " +element.price +"€ x "+ priceMultiplier +" personnes" +
                       "                                         </div>\n" +
                       "                                        <div class=\"col-4 price\">\n" +
                       "                                            <strong>"+parseInt(element.price)*priceMultiplier +" €</strong>\n" +
                       "                                        </div>\n" +
                       "\n" +
                       "                                    </div>\n" +
                       "                                </div>";

        animTabs = animTabs + animElem;
        budgetTotal = budgetTotal + parseInt(parseInt(element.price)*priceMultiplier);
      },this);

      if(AnimItems.length > 0) {
        $('.offer .item[data-tab="anim"]').html('');
        $('.offer .item[data-tab="anim"]').html(animTabs);
      }
      else {
        $('.offer .item[data-tab="anim"]').html('Aucune activité n\'a été sélectionnée');
      }

      remainingBudget = overallBudget - budgetTotal;
      budgetTotalPerPer = budgetTotal / priceMultiplier;

      $('.budget-total-value').html("<strong>"+budgetTotal+" € HT</strong>");
      $('.budget-per-par-value').html("<strong>"+budgetTotalPerPer+" € HT</strong>");
      $('.budget-remaining-value').html("<strong>"+remainingBudget+" € HT</strong>");

    };
  }

})(jQuery, Drupal);

