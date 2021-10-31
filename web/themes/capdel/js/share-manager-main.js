(function ($, Drupal) {
  Drupal.behaviors.shareScripts = {
    attach: function (context, settings) {
      "use strict";
      //wrap in document to execute once
      $(document,context).once('shareScripts').each(function () {
        
        $('.copy-link-btn').attr('data-clipboard-text',window.location.href);
        var clipboard = new ClipboardJS('.copy-link-btn', {
          container: document.getElementById('sharePopup')
        });

        clipboard.on('success', function(e) {
          $('#sharePopup').modal('hide');
        });

        var OFFRE_TEXT = "Bonjour, je recommande cette offre ${title} : ${link}";
        var DETAIL_TEXT = "Bonjour, je recommande ce lieu ${title} : ${link}";
        var TEXT = '';

        var parsedUrl = new URL(window.location.href);
        if(parsedUrl.pathname.indexOf('conf/lieux') !== -1) {
          TEXT = DETAIL_TEXT;
        }
        else {
          TEXT = OFFRE_TEXT;
        }

        var integrationHelper = new IntegrationHelper();
        integrationHelper.setTemplates(TEXT);
        
        a2a_config.callbacks.push({
          share: function(share_data) {
            share_data.title = share_data.title.split(' | ')[0];
            share_data.url = share_data.url.split('?')[0];

            if(share_data.service === "Email") {
              share_data.stop = true;

              var modifiedText = TEXT.replace('${title}',share_data.title);
              modifiedText = modifiedText.replace('${link}', share_data.url);

              var mailtoText = "mailto:?to=&subject="+share_data.title+"&body="+modifiedText;
              var anchor = document.createElement('a');
              anchor.href = mailtoText;
              anchor.click();
            }

            return share_data;
          }
        });

      });
    }
  };

  function IntegrationHelper() {
    this.setTemplates = function (text) {
      a2a_config.templates.twitter = {
        text: text
      };

      a2a_config.templates.facebook = {
        quote: text
      };
    };

    this.copyToClipboard = function(str) {

    };
  }

})(jQuery, Drupal);

