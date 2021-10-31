(function ($, Drupal) {
  Drupal.behaviors.reservationScripts = {
    attach: function (context, settings) {
      "use strict";
      //wrap in document to execute once
      $(document,context).once('eventPage').each(function () {
        var helper = new DomLocalStorageBroker();

        var popup = url('?popup');
        if(popup){
          $('#'+popup).modal('show');
        }

        $('.reservation-form .login-button[data-target="#loginModal"]').on('click',function (e) {
          e.preventDefault();
          $('#loginModal').modal('show');
        });

        $('.reservation-form .reservation-button-anonym[data-target="#eventReservationForm"]').on('click',function (e) {
          e.preventDefault();
          var isFormValidated = helper.validateForm();
          if(!isFormValidated) {
            e.stopPropagation();
            return;
          }
          helper.buildAnonymReservationForm();

        });

        $('#blankModal .clean-error').unbind().on('click',function(e) {
          $('#blankModal .validation-error').addClass('hidden').html(helper.VALIDATION_EMPTY);
          $('#blankModal .validation-error-desc').addClass('hidden').html(helper.VALIDATION_EMPTY);
        });

        $('.reservation-form .reservation-button').on('click',function (e) {
          e.preventDefault();
          var isFormValidated = helper.validateForm();
          if(!isFormValidated) {
            e.stopPropagation();
            return;
          }
          helper.makeReservation(e.target.dataset);
        });

        $('#bookingModal .btn[data-target="reset"]').on('click',function (e) {
          location.href='/reservations';
        });
      });

      $(document).ready(function (){
        $('#event-reservation-form').unbind().on('submit',function () {
          if (this.checkValidity() !== false) {
            $('#eventReservationForm').modal('hide');
            $('#bookingWait').modal('show');
          }
        });
      });
    }
  };

  function DomLocalStorageBroker() {

    this.VALIDATION_PARTICIPANTS = 'Merci de choisir le nombre de participants.';
    this.VALIDATION_PARTICIPANTS_LESS_THAN_10 = 'Cette offre n’est pas adaptée à la taille de votre groupe.\n';
    this.VALIDATION_PARTICIPANTS_LESS_THAN_10_DESC = '<a href="/contact" class="ml-2">Contactez-nous pour une solution personnalisée <i class="icon-angle-right ml-2"></i></a>';
    this.VALIDATION_DATE = 'Merci de renseigner une date au format JJ/MM/AAAA';
    this.VALIDATION_EMPTY = '';

    this.makeReservation = function(dataset) {
      $('#bookingWait').modal('show');
      var d = new Date,
          dformat = [d.getMonth()+1,
                     d.getDate(),
                     d.getFullYear()].join('/')+' '+
                    [d.getHours(),
                     d.getMinutes(),
                     d.getSeconds()].join(':');


      var preparedObject = {};

      var participantsParam = $('.event-par').val();
      var participants = null;
      if(participantsParam) {
        participants = participantsParam;
      }

      var eventPrice = $('.event-details .event-price').html();

      var date = $('.reservation-form .event-date input').val();
      var title = dataset.title;
      var enitity = dataset.nid;
      var user = dataset.uid;

      preparedObject.entities = [enitity];
      preparedObject.user = user;
      preparedObject.title = title;
      preparedObject.type = 'event';
      preparedObject.additionalInfo = {
        'participants number' : participants,
        'date' : date,
        'reservation date' : dformat,
        'url' : window.location.href,
        'eventPrice' : eventPrice,
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
    };

    this.validateForm = function () {
      var participantsParam = $('.event-par').val();
      var reservationDate = $('.reservation-date').val();
      var regex = /(((0|1)[0-9]|2[0-9]|3[0-1])\/(0[1-9]|1[0-2])\/((19|20)\d\d))$/;
      if(!regex.test(reservationDate)) {
        $('#blankModal .validation-error').removeClass('hidden').html(this.VALIDATION_DATE);
        $('#blankModal').modal('show');
        return false;
      }
      if(!participantsParam.trim()){
        $('#blankModal .validation-error').removeClass('hidden').html(this.VALIDATION_PARTICIPANTS);
        $('#blankModal').modal('show');
        return false;
      }
      else if(participantsParam < 10) {
        $('#blankModal .validation-error').removeClass('hidden').html(this.VALIDATION_PARTICIPANTS_LESS_THAN_10);
        $('#blankModal .validation-error-desc').removeClass('hidden').html(this.VALIDATION_PARTICIPANTS_LESS_THAN_10_DESC);
        $('#blankModal').modal('show');
        return false;
      }
      return true;
    }.bind(this);

    this.buildAnonymReservationForm = function () {
      var title = jQuery('.title h1').html(),
       price = jQuery('.price-tag').html(),
       category = jQuery('.category-title').html(),
       event_url = jQuery('#form_dest').val(),
       date = $('.reservation-form .event-date input').val(),
       participantsParam = (jQuery('#event-par option:selected').html()),
       participants = "";

      if(typeof price === "undefined") {
        price = "";
      }

      if(participantsParam !== "&nbsp;") {
        participantsParam = jQuery('.event-par').val() ;
        participants = participantsParam + " participants";
      }

      //assign the values to the hidden form fields
      jQuery('#edit-event-name').attr('value',title);
      jQuery('#edit-event-date').attr('value',date);
      jQuery('#edit-event-budget').attr('value',price);
      jQuery('#edit-event-pax').attr('value',participantsParam);
      jQuery('#edit-event-url').attr('value',event_url);

      var priceWrapper = jQuery('.price-wrapper').html();

      if(typeof priceWrapper === "undefined") {
        priceWrapper = "";
      }

      //build the template form
      this.buildResponseTemplate(title,category, priceWrapper,date,participants);

    };

    this.buildResponseTemplate = function (title,category,price,date,participants) {
      var elem = "<div class=\"card mb-3\">\n" +
                     "                                    <div class=\"row\">\n" +
                     "                                        <div class=\"col-8\">\n" +
                     "                                            <span class=\"title\">"+ title + "</span>\n" +
                     "                                            <span class=\"category\">"+ category+"</span>\n" +
                     "                                        </div>\n" +
                     "                                    </div>\n" +
                     "                                    <div class=\"row align-items-end single\">\n" +
                     "                                        <div class=\"col-8\">\n" +
                     "                                            " + participants +
                     "                                         </div>\n" +
                     "\n" +
                     "                                    </div>\n" +
                     "                                    <div class=\"row align-items-end single\">\n" +
                     "                                        <div class=\"col-8\">\n" +
                     "                                            " + date +
                     "                                          </div>\n" +
                 "                                        <div class=\"col-8\">\n" +
                 "                                            " + price +
                 "                                          </div>\n" +
                     "                                    </div>\n" +
                         "                                </div>";

      $('.offer .item.reservation-details').html(elem);
    }

  }

})(jQuery, Drupal);

