(function ($, Drupal) {
  Drupal.behaviors.configurateurScriptsPlaceDetails = {
    attach: function (context, settings) {
      "use strict";
      // wrap in document to execute once
      $(document, context).once('configurateurScriptsPlaceDetails').each(function () {
        $(document).ready(function () {

          var helper = new OperationHelper();

          helper.initReservationbutton();

          $('.place-reservation-button').on('click', function (e) {

            e.preventDefault();
            e.stopPropagation();

            if(typeof e.currentTarget.dataset.nid !== "undefined" ) {

              var data = e.currentTarget.dataset;
              var item = {
                id:data.nid,
                bundle: 'conf_lieu',
                count:0,
                type:'place',
                price:0,
                title:data.title,
                destination:data.destination || null,
                category:data.category || null,
              };

              var savedItemsArray = sessionStorage.getObj('savedItems') || [];

              //check if you have lieu declared in ss;
              var lieuElem = savedItemsArray.findIndex(function (obj) {
                if (obj.bundle == item.bundle) {
                  return obj;
                }
              });

              //we have the saved items , we have the item, we have the result
              //if the place is in the ss, now divide into actions

              //if we have lieu in ss - remove it;
              if (lieuElem !== -1) {
                //this is the place to get new place details
                if(data.action === 'add') {

                  //get available options
                  var itemData = helper.getPlaceData(item.id);
                  if (itemData !== "") {

                    // handle Menus
                    var selectedMenus  = helper.getSavedItemIdByBundle(savedItemsArray, 'configurateur_statics', 'menu');
                    var availableMenus = helper.getIdsFromMenuStructure(itemData.menu);
                    var priceTier      = Math.floor((data.pax) / 10) - 1;
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

                // finally remove the old place from the savedItems sessionStorage
                helper.removeItem(savedItemsArray[lieuElem].id);

                //update savedItemsArray
                savedItemsArray = sessionStorage.getObj('savedItems') || [];

                if(data.action === 'add') {
                  //push new action
                  savedItemsArray.push(item);
                  sessionStorage.setObj('savedItems',savedItemsArray);

                }
              }
              else {
                if(data.action === 'add') {
                  savedItemsArray.push(item);
                  sessionStorage.setObj('savedItems',savedItemsArray);
                }
              }

              var result  = "";
              if(data.action === 'add') {
                result = helper.insertParam('sel_place',e.currentTarget.dataset.nid);
              }
              else {
                result = helper.removeParam('sel_place',document.location.href);
              }

              helper.initReservationbutton(result);
              document.location.href = '/configurateur' + result;
            }
          });
        });

        function OperationHelper() {
          this.insertParam = function (key, value)
          {
            key = encodeURI(key); value = encodeURI(value);

            var kvp = document.location.search.substr(1).split('&');

            var i=kvp.length; var x; while(i--)
          {
            x = kvp[i].split('=');

            if (x[0]==key)
            {
              x[1] = value;
              kvp[i] = x.join('=');
              break;
            }
          }

            if(i<0) {kvp[kvp.length] = [key,value].join('=');}

            //this will reload the page, it's likely better to store this until finished
            return ('?' + kvp.join('&'));
          };

          this.removeParam = function (key, sourceURL) {
            var rtn = sourceURL.split("?")[0],
                param,
                params_arr = [],
                queryString = (sourceURL.indexOf("?") !== -1) ? sourceURL.split("?")[1] : "";
            if (queryString !== "") {
              params_arr = queryString.split("&");
              for (var i = params_arr.length - 1; i >= 0; i -= 1) {
                param = params_arr[i].split("=")[0];
                if (param === key) {
                  params_arr.splice(i, 1);
                }
              }
              rtn = "?" + params_arr.join("&");
            }
            return rtn;
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

          this.getIdsFromMenuStructure = function (menuObject) {
            var valuesArray = [];
            Object.keys(menuObject).map(function(key,index) {
              valuesArray.push(menuObject[key].id);
            });
            return valuesArray;
          };

          this.removeItem = function (id) {
            var savedItemsArray = sessionStorage.getObj('savedItems') || [];
            savedItemsArray = $.grep(savedItemsArray, function (e) {
              return e.id != id;
            });
            sessionStorage.setObj('savedItems',savedItemsArray);
          };

          this.initReservationbutton = function (externalQuery) {
            var addButton = $('.place-reservation-button.add');
            var removeButton = $('.place-reservation-button.remove');

            var urlParams = "";

            if(typeof externalQuery !== "undefined") {
              urlParams = new URLSearchParams(externalQuery);
            }
            else {
              urlParams = new URLSearchParams(window.location.search);
            }

            var myParam = urlParams.get('sel_place');

            if(typeof addButton[0] !== "undefined") {
               var currentID = addButton[0].dataset.nid;

               if(myParam === null || currentID !== myParam) {
                 //add button show
                 addButton.prop('hidden',false);
                 removeButton.prop('hidden',true);
               }
               if(currentID === myParam) {
                 //remove button show
                 removeButton.prop('hidden',false);
                 addButton.prop('hidden',true);
               }
            }
          }
        }

      });
    }
  };
})(jQuery, Drupal);