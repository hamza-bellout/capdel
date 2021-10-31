(function ($, Drupal) {
  Drupal.behaviors.buttonsScripts = {
    attach: function (context, settings) {
      "use strict";

      $(document, context).once('buttonScript').each(function () {
        setTimeout(function() {
          $("#header .btn").click(function (ev) {
            if (this.href.includes("user/login")) {
              ev.preventDefault();
              $('#loginModal').modal('show');
            }
          });

          $("#loginModal .btn").click(function (ev) {
            $("#user-login-form").submit();
          });

          $("#user-register-form").validate({
            errorElement: 'div',
            errorClass: 'invalid-feedback',
            ignoreTitle: true,
            highlight: function (element) {
              $(element).addClass('is-invalid').removeClass('is-valid');
            },
            unhighlight: function (element) {
              $(element).removeClass('is-invalid').addClass('is-valid');
            }
          });

          $.extend($.validator.messages, {
            required: 'Ce champ est obligatoire',
            email: 'Merci de rentrer une adresse mail valide'
          });

          $("#user-form").validate({
            highlight: function (element) {
              $(element).addClass('is-invalid').removeClass('is-valid');
            },
            unhighlight: function (element) {
              $(element).removeClass('is-invalid').addClass('is-valid');
            }
          });

          $("#registerModal .button").click(function (ev) {
            var form = $("#user-register-form");
            if (form.valid()) {
              $("#emailExists").addClass("hidden");
              $("#notAllFields").addClass("hidden");
              $("#emailNotMatch").addClass("hidden");
              var confMail = form.find("input[id^='confirm-email']").val();
              var mail = form.find("input[id^='edit-mail']").val();

              if (confMail === mail) {
                var helper = new userHelper();

                helper.checkIfExists(mail, form);
                ev.preventDefault();
              } else {
                $("#emailNotMatch").removeClass("hidden");
                form.find("input[id^='confirm-email']").addClass('is-invalid');
                form.find("input[id^='edit-mail']").addClass('is-invalid');
                ev.preventDefault();
              }
            } else {
              ev.preventDefault();
              //alert("There are mandatory fields that have not been properly filled in");
              $("#notAllFields").removeClass("hidden");
            }
          });

          $("#registerModal .error").addClass("hidden");
        }, 500);
      });
    }
  };

  function userHelper() {
    this.checkIfExists = function(mail, form) {
      var user = {};
      user['mail'] = mail;

      $.get('/rest/session/token')
        .done(function (data) {
          var csrfToken = data;
          $.ajax({
            url: '/api/v1.0/custom/user?_format=json',
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken,
            },
            data: JSON.stringify(mail),
            success: function (response) {
              console.log('success');
              console.log(response.data);
              form.find("input[id^='confirm-email']").addClass('is-invalid');
              form.find("input[id^='edit-mail']").addClass('is-invalid');
              $("#emailExists").removeClass("hidden");

            }.bind(this),
            error: function (response) {
              console.log('fail');
              console.log(response.data);
              form.submit();

            }.bind(this)
          });
        }.bind(this));
    };
  }

})(jQuery,Drupal);
