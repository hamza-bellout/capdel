(function ($, Drupal) {
  Drupal.behaviors.configurateurScripts = {
    attach: function (context, settings) {
      "use strict";
      var helper = new DomLocalStorageBroker(settings);
      $(document).ready(function (){
          $('#edit-participant').unbind().on('focusout', function () {
              helper.showValidationModal();
          });

        $('#edit-type').on('change', function () {
            return helper.showTaxonomyDescriptionText();
        });

        $('#blankModal .blank-close').unbind().on('click', function () {
          $('#edit-participant').val('10');
        });

        $('#blankModal .clean-error').unbind().on('click',function(e) {
          $('#blankModal .validation-error').addClass('hidden').html(helper.VALIDATION_EMPTY);
        });
      });
      //wrap in document to execute once
      $(document,context).once('confPage').each(function () {
        $(document).ready(function (){

          // show description text based on the selected event type
          helper.showTaxonomyDescriptionText();

          //handle the submit button validation
          $('#views-exposed-form-configurateur-view-conf-lieu').on('submit',function (e) {
            return helper.showValidationModal();
          });
        });

          //set proper action state based on localstorage
          helper.setDomActions(true);

          $('a.action').on('click', function (e) {
            e.preventDefault();
            helper.handleActions(e.target.dataset);
          });

          $(document).on('click', function(e) {
            if (!$(e.target).closest('.conf-modal-backdrop').length) {
              $('.modal .inner').hide();
            }
          });

          //listen to form changes
          helper.changeLinksFromURL();


          $('a.resume-tab').on('click', function (e) {
            e.preventDefault();
            helper.prepareResumeModal();

          });

          $('#createEventModal button[data-target="#send"]').on('click', function (e) {
            var name = $('#createEventModal #name:visible').val();
            helper.makeReservation(name);
          });

          $('#resumeModal button[data-target="#createEventModal"]').on('click', function (e) {
            var isFormValidated = helper.validateFrom(sessionStorage.getObj('savedItems'));
            if(!isFormValidated) {
              e.preventDefault();
              e.stopPropagation();
              return;
            }
          });

          $('#bookingModal button[data-target="reset"]').on('click', function (e) {
            helper.clearConfig();
            location.href='/reservations';
          });

          $('#bookingModalFail button[data-target="reload"]').on('click', function (e) {
            helper.redirect();
          });
      });
    }
  };

  function DomLocalStorageBroker(settings) {
    var overallBudget;
    var menuItems = [];
    var LieuItems = [];
    var OptionsItems = [];
    var TBItems = [];
    var AnimItems = [];
    var budgetTotal = 0;
    var remainingBudget = 0;
    var budgetTotalPerPer = 0;

    this.VALIDATION_TB_ANIM_OPT = 'Vous devez choisir au moins un team building, une animation ou une option.';
    this.VALIDATION_MENU = 'Vous ne pouvez pas choisir un lieu sans menu ou boisson.';
    this.VALIDATION_EMPTY = '';

    //defining enums to maintain tab order in menu-local-tasks
    var tabs = Object.freeze({
      "LIEU":0,
      "MENU":1,
      "TB":2,
      "ANIM":3,
      "OPTIONS":4
    });

    this.validateFrom = function (savedItemsArray) {
      //no items selected, display error
      if (savedItemsArray == null || savedItemsArray.length == 0) {
        $('#blankModal .validation-error').removeClass('hidden').html(this.VALIDATION_TB_ANIM_OPT);
        $('#blankModal').modal('show');
        $('#resumeModal').modal('hide');
        return false;
      }

      //validate place and menu
      var lieuElem = savedItemsArray.findIndex(function (obj) {
        if (obj.bundle === 'conf_lieu') {
          return obj;
        }
      });

      //if we have lieu in ss - check menu;
      if (lieuElem !== -1) {
        var menuElem = savedItemsArray.findIndex(function (obj) {
          if (obj.type === 'menu') {
            return obj;
          }
        });
        //menu not found, so return
        if (menuElem === -1) {
          $('#blankModal .validation-error').removeClass('hidden').html(this.VALIDATION_MENU);
          $('#blankModal').modal('show');
          $('#resumeModal').modal('hide');
          return false;
        }
      }
      //The user needs to choose at least one of this items : TB, Animation,
      // Option
      var choosenElement = savedItemsArray.findIndex(function (obj) {
        if (obj.bundle === 'configurateur_tb_and_anim' ||
            obj.bundle === 'configurateur_animations' ||
            (obj.bundle === 'configurateur_statics' && obj.type === 'option')) {
          return obj;
        }
      });
      //menu not found, so return
      if (choosenElement === -1) {
        $('#blankModal .validation-error').removeClass('hidden').html(this.VALIDATION_TB_ANIM_OPT);
        $('#blankModal').modal('show');
        $('#resumeModal').modal('hide');
        return false;
      }
      return true;
    }.bind(this);

    this.makeReservation = function(name) {
      $('#bookingWait').modal('show');
      var savedItemsArray = sessionStorage.getObj('savedItems');

      if(savedItemsArray == null || savedItemsArray.length == 0) {
        $('#bookingModalFail').modal('show');
        $('#resumeModal').modal('hide');
        return;
      }

      var idsArray = savedItemsArray.map(function(elem) {return elem.id});

      var d = new Date,
          dformat = [d.getMonth()+1,
                     d.getDate(),
                     d.getFullYear()].join('/')+' '+
                    [d.getHours(),
                     d.getMinutes(),
                     d.getSeconds()].join(':');


      var preparedObject = {};
      preparedObject.entities = idsArray;
      preparedObject.user = $('nav.navbar.main-nav')[0].dataset.uid;

      if(name == "") {
        name = preparedObject.user + ' - Conf ' + dformat;
      }

      var selected = $('form#views-exposed-form-configurateur-view-conf-lieu').serialize().split("&");
      var selectedSplit = {};
      $.each( selected, function( key, value ) {
        var splitArray = value.split('=');
        selectedSplit[splitArray[0]] = splitArray[1];
      });

      preparedObject.title = name;
      preparedObject.type = 'configurator';
      preparedObject.additionalInfo = {
        'budgetTotal' : budgetTotal + remainingBudget,
        'budgetTotalPackage' : budgetTotal,
        'remainingBudget' : remainingBudget,
        'budgetTotalPerPer' : budgetTotalPerPer,
        'items' : {
          'lieuItems' : LieuItems,
          'menuItems' : menuItems,
          'TBItems' : TBItems,
          'AnimItems' : AnimItems,
          'OptionsItems' : OptionsItems
        },
        'selected values' : $('form#views-exposed-form-configurateur-view-conf-lieu').serialize().split("&"),
        'selected_values_formatted' : selectedSplit,
        'date' : $('#edit-format-date').val()
      };

      $.get('/rest/session/token')
        .done(function (data) {
          var csrfToken = data;
          $.ajax({
            url: '/api/v1.0/custom/reservation?_format=json',
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken,
            },
            data: JSON.stringify(preparedObject),
            success: function (response) {
              $('#bookingWait').modal('hide');
              $('#bookingModal').modal('show');
            }.bind(this),
            error: function (response) {
              $('#bookingWait').modal('hide');
              $('#bookingModalFail').modal('show');
            }.bind(this)
          });
        }.bind(this));

    }.bind(this);

    this.clearConfig = function () {
      sessionStorage.removeItem('savedItems');
    };

    this.redirect = function () {
      location.reload(true);
    };

    this.prepareResumeModal = function() {
      overallBudget = 0;
      menuItems = [];
      LieuItems = [];
      OptionsItems = [];
      TBItems = [];
      AnimItems = [];
      budgetTotal = 0;
      remainingBudget = 0;
      budgetTotalPerPer = 0;

      var savedItemsArray = sessionStorage.getObj('savedItems') || [];
      var priceMultiplier = jQuery('#edit-participant').val();
      var selectedDate = jQuery('#edit-format-date').val() || "";

      if(priceMultiplier != "") {
        priceMultiplier = parseInt(priceMultiplier);
      }
      else {
        priceMultiplier = 1;
      }

      var pricePerPerson = jQuery('#edit-prix').val();

      if(pricePerPerson != "") {
        pricePerPerson = parseInt(pricePerPerson);
      }
      else {
        pricePerPerson = 50;
      }

      overallBudget = priceMultiplier * pricePerPerson;

      savedItemsArray.forEach(function (element) {
        switch (element.bundle) {
          case 'conf_lieu' :
            LieuItems.push(element);
            break;
          case 'configurateur_statics' :
            switch (element.type) {
              case 'option' :
                OptionsItems.push(element);
                break;
              case 'menu' :
              case 'drink' :
                menuItems.push(element);
                break;
            }
            break;
          case 'configurateur_animations' :
            AnimItems.push(element);
            break;
          case 'configurateur_tb_and_anim' :
            TBItems.push(element);
            break;
        }
      });

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
                       "                                    <div class=\"remove\"><i class=\"icon-close\" data-id='"+element.id+"' data-type='" +element.bundle+"'></i></div>\n" +
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
        if (element.count > 0) {
          priceTag = element.price +"€ x "+ element.count +"  personnes";
        }
        else {
          element.count = 1;
        }

        var menuElem = "<div class=\"card mb-3\">\n" +
                       "                                    <div class=\"remove\"><i class=\"icon-close\" data-id='"+element.id+"' data-type='" +element.bundle+"'></i></div>\n" +
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
          $('.offer .item[data-tab="place"]').html(lieuTabs + "Aucune formule Menu et Boissons sélectionnée");
        }
      }
      else {
        if(menuItems.length > 0) {
          $('.offer .item[data-tab="place"]').html("Aucun lieu sélectionné" + menuTabs);
        }
        else {
          $('.offer .item[data-tab="place"]').html("Aucun lieu sélectionné <br/>Aucune formule Menu et Boissons sélectionnée");
        }
      }

      OptionsItems.forEach(function (element) {

        if (element.category == null) {
          element.category = '';
        }


        var priceTag = "";
        if (element.count > 0) {
          priceTag = element.price +"€ x "+ element.count;
        }
        else {
          element.count = 1;
        }

        var optElem = "<div class=\"card mb-3\">\n" +
                      "                                    <div class=\"remove\"><i class=\"icon-close\" data-id='"+element.id+"' data-type='" +element.bundle+"'></i></div>\n" +
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
        $('.offer .item[data-tab="options"]').html("Aucune option sélectionnée");
      }

      TBItems.forEach(function (element) {

        if (element.category == null) {
          element.category = '';
        }
        if (element.destination == null) {
          element.destination = '';
        }

        var tbElem = "<div class=\"card mb-3\">\n" +
                     "                                    <div class=\"remove\"><i class=\"icon-close\" data-id='"+element.id+"' data-type='" +element.bundle+"'></i></div>\n" +
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
        $('.offer .item[data-tab="tb"]').html("Aucune activité sélectionnée");
      }

      AnimItems.forEach(function (element) {

        if (element.category == null) {
          element.category = '';
        }
        if (element.destination == null) {
          element.destination = '';
        }

        var animElem = "<div class=\"card mb-3\">\n" +
                     "                                    <div class=\"remove\"><i class=\"icon-close\" data-id='"+element.id+"' data-type='" +element.bundle+"'></i></div>\n" +
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
        $('.offer .item[data-tab="anim"]').html("Aucune animation sélectionnée");
      }

      remainingBudget = overallBudget - budgetTotal;
      budgetTotalPerPer = budgetTotal / priceMultiplier;

      $('.budget-total-value').html("<strong>"+budgetTotal+" € HT</strong>");
      $('.budget-per-par-value').html("<strong>"+budgetTotalPerPer+" € HT</strong>");
      $('.budget-remaining-value').html("<strong>"+remainingBudget+" € HT</strong>");


      /*
      Setting up the listener after creating the elements.
      If the user will delete dependent item - view will reload.
       */
      $('.card .remove .icon-close').on('click', function (e) {
        var id = e.target.dataset.id,
            type = e.target.dataset.type;

        //return to defult prices after deleting place entity from selection
        if(type === 'conf_lieu') {
          this.revertMenuPricesToDefault();
        }

        //adjusting JS elements
        this.removeFromSelection(id,type);
        this.prepareResumeModal();
        this.setDomActions(false);

      }.bind(this));

    };

    this.removeFromSelection = function (id,type) {
      this.manageUrls(type,id,'remove');
      this.removeItem(id);
      this.removeSelection(id);
      this.manageCounterBadge(-1);

      // decide if we need to reload the page
      if(settings.hasOwnProperty('views') && settings.views.hasOwnProperty('ajaxViews')) {
          Object.keys(settings.views.ajaxViews).forEach(function (elem) {
            var display = settings.views.ajaxViews[elem].view_display_id;
             if(display !== 'conf_options' && display !== 'conf_animations') {
               var urlData = sessionStorage.getObj('urlData');

               var query = this.buildSearchQueryString(urlData.query);
               window.location.href = window.location.pathname + query;
             }
          }.bind(this));
        }
    };

    this.setDomActions = function (refresh) {
      var savedItemsArray = sessionStorage.getObj('savedItems');
      if(savedItemsArray && savedItemsArray.length != 0) {
        if(refresh === true) {
          this.manageCounterBadge(savedItemsArray.length);
          this.updateTabLabels(savedItemsArray);
        }
        savedItemsArray.forEach(function (elem) {
          this.showSelection(elem.id);
          this.populateInputCount(elem.id, elem.count);
        }.bind(this));
      }
    };

    this.updateTabLabels = function(savedItemsArray) {
      var tabLabels = $('.steps .row').children().find('.w-100 .step-desc');
      var menuSteps = $('a.step');
      var menuCount = 0,
          drinkCount = 0,
          optionCount = 0,
          tbCount = 0,
          placeCount = 0,
          animCount = 0;
      var placeName = "",
          menuName = "",
          fullMenuName = "",
          drinkName = "",
          optionsName = "",
          tbName = "",
          animName = "";
      var defaultPlaceName = "Choisir un lieu";
      var defaultMenuName = "Ajouter un menu";
      var defaultTBName = "Ajouter une activité";
      var defaultAnimName = "Ajouter une animation";
      var defaultOptionName = "Ajouter une option";

      savedItemsArray.forEach(function (elem) {
        if(elem.bundle === "configurateur_statics") {
          if(elem.type === "menu") {
            menuCount++;
          }
          else if (elem.type === "drink"){
            drinkCount++;
          }
          else {
            optionCount++;
          }
        }
        else if(elem.bundle === "conf_lieu") {
          placeName = elem.title;
          if(elem.destination !== null) {
            placeName = placeName + ', ' + elem.destination;
          }
          placeCount++;
          tabLabels.eq(tabs.LIEU).html(placeName);
        }
        else if(elem.bundle === "configurateur_tb_and_anim") {
          tbCount++;
        }
        else if(elem.bundle === "configurateur_animations") {
          animCount++;
        }
      });
      // if user unselected place, return to default
      if(placeCount == 0) {
        tabLabels.eq(tabs.LIEU).html(defaultPlaceName);
        menuSteps.eq(tabs.LIEU).removeClass('selected');
      }
      else {
        menuSteps.eq(tabs.LIEU).addClass('selected');
      }
      // update menu tab
      if(menuCount > 0 || drinkCount > 0) {
        menuName = this.buildWordVariety(menuCount,'Menu');
        drinkName = this.buildWordVariety(drinkCount,'Boisson');

        if(menuName && drinkName) {
          fullMenuName = menuName + ' & ' + drinkName;
        }
        else if(menuName && drinkName === false) {
          fullMenuName = menuName;
        }
        else if(drinkName && menuName === false) {
          fullMenuName = drinkName;
        }
        tabLabels.eq(tabs.MENU).html(fullMenuName);
        menuSteps.eq(tabs.MENU).addClass('selected');
      }
      else {
        tabLabels.eq(tabs.MENU).html(defaultMenuName);
        menuSteps.eq(tabs.MENU).removeClass('selected');
      }
      // update option tab
      if(optionCount > 0) {
        optionsName = this.buildWordVariety(optionCount,'Option');
        tabLabels.eq(tabs.OPTIONS).html(optionsName);
        menuSteps.eq(tabs.OPTIONS).addClass('selected');
      }
      else {
        tabLabels.eq(tabs.OPTIONS).html(defaultOptionName);
        menuSteps.eq(tabs.OPTIONS).removeClass('selected');
      }
      // update tb tab
      if(tbCount > 0) {
        tbName = this.buildWordVariety(tbCount,'Team Building');
        tabLabels.eq(tabs.TB).html(tbName);
        menuSteps.eq(tabs.TB).addClass('selected');
      }
      else {
        tabLabels.eq(tabs.TB).html(defaultTBName);
        menuSteps.eq(tabs.TB).removeClass('selected');
      }
      // update anim tab
      if(animCount > 0) {
        animName = this.buildWordVariety(animCount,'Animation');
        tabLabels.eq(tabs.ANIM).html(animName);
        menuSteps.eq(tabs.ANIM).addClass('selected');

      }
      else {
        tabLabels.eq(tabs.ANIM).html(defaultAnimName);
        menuSteps.eq(tabs.ANIM).removeClass('selected');
      }
    };

    this.buildWordVariety = function (count,word) {
      //build the menu name
      var name = "";

      if(count == 1) {
        name = count + ' ' + word;
      }
      else if(count > 1) {
        name = count + ' ' + word + 's';
      }
      else {
        return false;
      }
      return name;
    };

    this.populateInputCount = function(id,count) {
      if(count) {
        $('.info-post .count-value[data-id="'+id+'"]').val(count);
      }
    };

    this.handleActions = function (dataset){
      this.manageUrls(dataset.bundle,dataset.id,dataset.action,dataset.type);

      if(dataset.action === 'remove') {
        if(dataset.bundle === 'conf_lieu') {
          this.revertMenuPricesToDefault();
        }
        this.removeItem(dataset.id);
        this.removeSelection(dataset.id);
        this.manageCounterBadge(-1);
      }
      else if(dataset.action === 'add') {
        var countValue = $('input.count-value[data-id='+dataset.id+']').val() || 0;
        var defaultPrice = dataset.defaultprice || 0;
        this.addItem({
            id:dataset.id,
            bundle: dataset.bundle,
            count:countValue,
            type:dataset.type,
            price:dataset.price,
            default_price:defaultPrice,
            title:dataset.title,
            destination:dataset.destination || null,
            category:dataset.category || null,
          });
        this.showSelection(dataset.id);
        this.manageCounterBadge(1);
      }
      else if(dataset.action === 'count-add') {

        var countValue = parseInt($('input.count-value[data-id='+dataset.id+']').val()) + 1;

        $('input.count-value[data-id='+dataset.id+']').val(countValue);

        //check if we have the value in the sessionStorage
        if($('.selection-add.hidden[data-id="'+dataset.id+'"]').length === 1) {
          var defaultPrice = dataset.defaultprice || 0;
          this.addItem({
            id:dataset.id,
            bundle:dataset.bundle,
            count:countValue,
            type:dataset.type,
            price:dataset.price,
            default_price:defaultPrice,
            title:dataset.title,
            destination:dataset.destination || null,
            category:dataset.category || null,
          })
        }
      }
      else if(dataset.action === 'count-minus') {
        var countValue = parseInt($('input.count-value[data-id='+dataset.id+']').val()) - 1;

        if(countValue < 0) {
          countValue = 0;
          //todo if 0, remove from the selection
        }

        $('input.count-value[data-id='+dataset.id+']').val(countValue);

        //check if we have the value in the sessionStorage
        if($('.selection-add.hidden[data-id="'+dataset.id+'"]').length === 1) {
          var defaultPrice = dataset.defaultprice || 0;
          this.addItem({
            id:dataset.id,
            bundle:dataset.bundle,
            count:countValue,
            type:dataset.type,
            price:dataset.price,
            default_price:defaultPrice,
            title:dataset.title,
            destination:dataset.destination || null,
            category:dataset.category || null,
          })
        }
      }
    };

    this.manageUrls = function(bundle,id,action,type) {
      var urlData = sessionStorage.getObj('urlData') || {};
      if((typeof urlData.query) === 'undefined') {
        if(location.search !== "") {
          urlData.query = this.parseQueryString(location.search.split('?')[1]);
        }
        else {
          urlData.query = {
            'type':'All',
            'date':'All',
            'destination':'All',
            'participant':'40',
            'prix':'1000'
          };
        }
      }
      else {
        if(location.search !== "") {
          //update urlData query
          var newUrlQuery = this.parseQueryString(location.search.split('?')[1]);
          for (var property in newUrlQuery) {
            if (newUrlQuery.hasOwnProperty(property)) {
              if(property !== "sel_menu" && property !== "sel_place") {
                urlData.query[property] = newUrlQuery[property];
              }
            }
          }
        }
      }
      if(action === 'add') {
        if(bundle === 'configurateur_statics' && typeof type !== "undefined" && type === 'menu') {
          urlData.query = this.addToArray(urlData.query,'sel_menu',id);
        }
        if(bundle === 'conf_lieu') {
          urlData.query = this.addToArray(urlData.query,'sel_place',id);
        }
        if(bundle === 'configurateur_tb_and_anim') {
          urlData.query = this.addToArray(urlData.query,'sel_tb',id);
        }
      }
      if(action === 'remove') {
        if(bundle === 'configurateur_statics') {
          urlData.query = this.removeFromArray(urlData.query,'sel_menu',id);
        }
        if(bundle === 'conf_lieu') {
          urlData.query = this.removeFromArray(urlData.query,'sel_place',id);
        }
        if(bundle === 'configurateur_tb_and_anim') {
          urlData.query = this.removeFromArray(urlData.query,'sel_tb',id);
        }
      }

      this.buildSearchQueryString(urlData.query);
      sessionStorage.setObj('urlData',urlData);

    };

    this.addToArray = function(existingValues, field, value) {
      if(typeof existingValues[field] !== 'undefined') {

        //place special treatment - only one value allowed
        if(field === 'sel_place' || field === 'sel_tb') {
          existingValues[field] = value;
          return existingValues;
        }

        //check if the value is in the string
        var exVal = existingValues[field].split(',');
        var found = (exVal.find(function (e) {
          return e == value
        }.bind(this)));

        if (!found) {
          existingValues[field] += ','+value;
        }
      }
      else {
        existingValues[field] = value;
      }
      return existingValues;
    };

    this.removeFromArray = function(existingValues, field, value) {
      if(typeof existingValues[field] !== 'undefined') {
        //check if the value is in the string
        var exVal = existingValues[field].split(',');
        var found = (exVal.find(function (e) {
          return e == value
        }.bind(this)));

        if (found !== false) {
          existingValues[field] = existingValues[field]
            .replace(','+found,'')
            .replace(found+',','')
            .replace(found,'');

          if(existingValues[field] == "") {
            delete existingValues[field];
          }
        }
      }
      return existingValues;
    };

    this.revertMenuPricesToDefault = function() {
      var savedItemsArray = sessionStorage.getObj('savedItems') || [];
      var selectedMenus  = this.getSavedItemIdByBundle(savedItemsArray, 'configurateur_statics', 'menu');

      $(selectedMenus).each(function (index, value) {
        var menuIndex = savedItemsArray.findIndex(function (obj) {
          if (obj.id == value) {
            return obj;
          }
        });

        if (menuIndex !== -1) {
          savedItemsArray[menuIndex].price = savedItemsArray[menuIndex].default_price;
        }
      });

      sessionStorage.setObj('savedItems', savedItemsArray);
    };

    this.buildSearchQueryString = function(existingValues) {
      var query = "?";

      Object.keys(existingValues).map(function(key) {
        query += key + '=' + (existingValues[key] || "") + "&";
        return existingValues[key];
      });

      this.changeLinksFromURL(query);
      return query;

    };

    this.manageCounterBadge = function(action) {
      var counterBadge =  $('.resume-tab .counter');
      var value = counterBadge.html();

      if(action > 0) {
        if(counterBadge.hasClass('hidden')) {
          counterBadge.removeClass('hidden');
        }

        if(value !== "") {
          value = parseInt(value);
        }
        value = value + parseInt(action);
        counterBadge.html(value);
      }
      else {
        if(value == "") {
          counterBadge.addClass('hidden');
        }
        else {
          value = parseInt(value) + parseInt(action);
          counterBadge.html(value);
          if(value <= 0) {
            counterBadge.addClass('hidden');
          }
        }
      }
    };

    this.showSelection= function (id) {
      $('.selection-remove[data-id="'+id+'"]').removeClass('hidden');
      $('.selection-add[data-id="'+id+'"]').addClass('hidden');
    };

    this.removeSelection= function (id) {
      $('.selection-remove[data-id="'+id+'"]').addClass('hidden');
      $('.selection-add[data-id="'+id+'"]').removeClass('hidden');
    };

    this.addItem = function (item) {
      var savedItemsArray = sessionStorage.getObj('savedItems') || [];

      //only one place is allowed
      if(item.bundle == "conf_lieu") {
        //check if you have lieu declared in ss;
        var lieuElem = savedItemsArray.findIndex(function (obj) {
          if (obj.bundle == item.bundle) {
            return obj;
          }
        });

        //if we have lieu in ss - remove it;
        if (lieuElem !== -1) {
          //this is the place to get new place details

          //get available options
          var itemData = this.getPlaceData(item.id);
          if (itemData !== "") {

            // handle Menus
            var selectedMenus  = this.getSavedItemIdByBundle(savedItemsArray, 'configurateur_statics', 'menu');
            var availableMenus = this.getIdsFromMenuStructure(itemData.menu);
            var priceTier      = Math.floor(parseInt($('#edit-participant').val()) / 10) - 1;
            var menuChanged    = false;

            $(selectedMenus).each(function (index, value) {
              if ($.inArray(value, availableMenus) > -1) {
                var menuIndex = savedItemsArray.findIndex(function (obj) {
                  if (obj.id == value) {
                    return obj;
                  }
                });

                if (menuIndex !== -1) {
                  menuChanged                      = true;
                  savedItemsArray[menuIndex].price = itemData.menu[value].prices[priceTier].value
                }
              }
            });

            if (menuChanged) {
              sessionStorage.setObj('savedItems', savedItemsArray);
            }

          }

          // finally remove the old place from the savedItems sessionStorage
          this.removeItem(savedItemsArray[lieuElem].id);
          this.removeSelection(savedItemsArray[lieuElem].id);
          this.manageCounterBadge(-1);

          //update savedItemsArray
          savedItemsArray = sessionStorage.getObj('savedItems') || [];
        }

        //heck if we have the menu item here and update the price
        var selectedMenus  = this.getSavedItemIdByBundle(savedItemsArray, 'configurateur_statics', 'menu');

        if(selectedMenus.length > 0) {
          var itemData = this.getPlaceData(item.id);
          if (itemData !== "") {

            // handle Menus
            var selectedMenus  = this.getSavedItemIdByBundle(savedItemsArray, 'configurateur_statics', 'menu');
            var availableMenus = this.getIdsFromMenuStructure(itemData.menu);
            var priceTier      = Math.floor(parseInt($('#edit-participant').val()) / 10) - 1;
            var menuChanged    = false;

            $(selectedMenus).each(function (index, value) {
              if ($.inArray(value, availableMenus) > -1) {
                var menuIndex = savedItemsArray.findIndex(function (obj) {
                  if (obj.id == value) {
                    return obj;
                  }
                });

                if (menuIndex !== -1) {
                  menuChanged                      = true;
                  savedItemsArray[menuIndex].price = itemData.menu[value].prices[priceTier].value
                }
              }
            });

            if (menuChanged) {
              sessionStorage.setObj('savedItems', savedItemsArray);
            }
          }
        }

        //push new action
        savedItemsArray.push(item);

        sessionStorage.setObj('savedItems',savedItemsArray);
        this.updateTabLabels(savedItemsArray);

        //update dom buttons
        this.setDomActions(false);
        return;

      }

      //only one tb is allowed
      if(item.bundle == "configurateur_tb_and_anim") {
        //check if you have lieu declared in ss;
        var tbElem = savedItemsArray.findIndex(function (obj) {
          if (obj.bundle == item.bundle) {
            return obj;
          }
        });

        //if we have tb in ss - remove it;
        if (tbElem !== -1) {
          // finally remove the old tb from the savedItems sessionStorage
          this.removeItem(savedItemsArray[tbElem].id);
          this.removeSelection(savedItemsArray[tbElem].id);
          this.manageCounterBadge(-1);

          //update savedItemsArray
          savedItemsArray = sessionStorage.getObj('savedItems') || [];
        }

        //push new action
        savedItemsArray.push(item);
        sessionStorage.setObj('savedItems',savedItemsArray);
        this.updateTabLabels(savedItemsArray);

        //update dom buttons
        this.setDomActions(false);
        return;
      }

      var itemElement = savedItemsArray.findIndex(function(obj) {
        if(obj.id == item.id) {
          return obj;
        }
      });

      if(itemElement !== -1) {
        savedItemsArray[itemElement] = item;
      }
      else {
        savedItemsArray.push(item);
      }

      sessionStorage.setObj('savedItems',savedItemsArray);
      this.updateTabLabels(savedItemsArray);
    };

    this.removeItem = function (id) {
      var savedItemsArray = sessionStorage.getObj('savedItems') || [];
      savedItemsArray = $.grep(savedItemsArray, function (e) {
        return e.id != id;
      });
      sessionStorage.setObj('savedItems',savedItemsArray);
      this.updateTabLabels(savedItemsArray);
    };

    this.changeLinksFromURL = function (externalQueryParams) {
      var queryParams = "";
      var external = false;

      if(typeof externalQueryParams !== 'undefined') {
        external = true;
        queryParams = externalQueryParams;
      }
      else {
        queryParams = location.search;

        if(queryParams == "") {
          queryParams = "?type=All&date=All&destination=All&participant=40&prix=1000";
        }
        // queryparams not empty
        else {
          var urlData = sessionStorage.getObj('urlData');
          if(urlData) {
            var newUrlQuery = this.parseQueryString(location.search.split('?')[1]);
            for (var property in urlData.query) {
              if (urlData.query.hasOwnProperty(property)) {
               if(!(newUrlQuery.hasOwnProperty(property))) {
                 newUrlQuery[property] = urlData.query[property];
               }
              }
            }
            return this.buildSearchQueryString(newUrlQuery);
          }
        }
      }

      //remove the 'page=d' parameter from the links -
      //not important during the page transitions between tabs
      queryParams = queryParams.replace(/page=\d+&?/gmi,'');

      var menuSteps = $('a.step');
      menuSteps.each(function (index) {
        if(external) {
          queryParams = externalQueryParams;
          queryParams = queryParams.replace(/page=\d+&?/gmi,'');
        }
        var href = (this.href).split('?');
        var host = href[0];
        this.href = host + queryParams;
      });

      var placeAnchors = $('a.conf-place-anchor');
      placeAnchors.each(function (index) {
        if(external) {
          queryParams = externalQueryParams;
          queryParams = queryParams.replace(/page=\d+&?/gmi,'');
        }
        var href = (this.href).split('?');
        var host = href[0];
        this.href = host + queryParams;
      });

    };

    this.parseQueryString = function( queryString ) {
      var params = {}, queries, temp, i, l;
      // Split into key/value pairs
      queries = queryString.split("&");
      // Convert the array of strings into an object
      for ( i = 0, l = queries.length; i < l; i++ ) {
        temp = queries[i].split('=');
        params[temp[0]] = temp[1];
      }
      return params;
    };

    this.showValidationModal = function () {
      var value = $('#edit-participant').val();
      if(value > 99 || value < 10) {
        $('#blankModal .modal-title').html('Le nombre de personne doit être compris entre 10 et 99.');
        $('#blankModal').modal('show');
        $('#edit-participant').val('10');
        return false;
      }
      else {
        return true;
      }
    };

    this.showTaxonomyDescriptionText = function () {
      var elements = $('.conf-menu-types-description.helper').children();
      var selectedValue = 'tid-' + $('#edit-type').val();
      $(elements).each(function (index) {
        if($(this).hasClass(selectedValue) && $(this).hasClass('hidden')) {
          $(this).removeClass('hidden');
        }
        if(!($(this).hasClass(selectedValue)) && !($(this).hasClass('hidden'))) {
          $(this).addClass('hidden');
        }
      });
      return true;
    };

    this.getPlaceData = function (id) {
      var returnResponse = "";
      $.ajax({
        url: '/rest/session/token',
        method: 'GET',
        async: false
      })
        .done(function (data) {
          var csrfToken = data;
          $.ajax({
            url: '/api/v1.0/custom/configurator/place/' + id + '?_format=json',
            async: false,
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken
            }
          })
            .done(function (response) {
              returnResponse = response;
            });
        });
      return returnResponse;
      };

    this.findIntersection = function (a,b) {
      var t;
      if (b.length > a.length) t = b, b = a, a = t; // indexOf to loop over shorter
      return a
        .filter(function (e) {
          return b.indexOf(e) > -1;
        })
        .filter(function (e, i, c) { // extra step to remove duplicates
          return c.indexOf(e) === i;
        })
    };

    this.getSavedItemIdByBundle = function (savedItemsArray, bundle, type) {
      return savedItemsArray.filter(function (item) {
        if(item.bundle === bundle) {
          if(item.bundle === 'configurateur_statics') {
            if (typeof type !== 'undefined' && item.type === type) {
              return true;
            }
            return false;
          }
          return true;
        }
      }).map(function (item) {
        return item.id;
      })
    };

    this.checkArrayInclude = function (sup, sub) {
      sup.sort();
      sub.sort();
      var i, j;
      for (i=0,j=0; i<sup.length && j<sub.length;) {
        if (sup[i] < sub[j]) {
          ++i;
        } else if (sup[i] == sub[j]) {
          ++i; ++j;
        } else {
          // sub[j] not in sup, so sub not subbag
          return false;
        }
      }
      // make sure there are no elements left in sub
      return j == sub.length;
    };

    this.arrayDifference = function (a1, a2) {
      var a = [], diff = [];
      for (var i = 0; i < a1.length; i++) {
        a[a1[i]] = true;
      }
      for (var i = 0; i < a2.length; i++) {
        if (a[a2[i]]) {
          delete a[a2[i]];
        } else {
          a[a2[i]] = true;
        }
      }
      for (var k in a) {
        diff.push(k);
      }
      return diff;
    };

    this.getIdsFromMenuStructure = function (menuObject) {
      var valuesArray = [];
      Object.keys(menuObject).map(function(key,index) {
        valuesArray.push(menuObject[key].id);
      });
      return valuesArray;
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


