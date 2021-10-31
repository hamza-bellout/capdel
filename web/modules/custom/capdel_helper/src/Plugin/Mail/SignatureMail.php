<?php

namespace Drupal\capdel_helper\Plugin\Mail;

use Drupal\capdel_helper\Helpers\MenuHelper;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Mail\Plugin\Mail\PhpMail;
use Drupal\Core\Url;

/**
 * Defines the default Drupal mail backend, using PHP's native mail() function.
 *
 * @Mail(
 *   id = "capdel_helper",
 *   label = @Translation("Signature Mail"),
 *   description = @Translation("Sends the message as HTML text, using PHP's native mail() function.")
 * )
 */
class SignatureMail extends PhpMail {

  /**
   * Concatenates and wraps the email body for plain-text mails.
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return array
   *   The formatted $message.
   */
  public function format(array $message) {
    // Join the body array into one string.
    $message['body'] = implode("\n\n", $message['body']);

    //ugly hack to fix cutting header From
    $this->fixHeaderForm($message);

    // Convert any HTML to plain-text.
    // $message['body'] = MailFormatHelper::htmlToText($message['body']);
    // Wrap the mail body for sending.
    $message['body'] = $this->wrapMail($message['body']);

    // Set HTML Headers
    $message['headers']['Content-Type'] = 'text/html; charset=UTF-8; format=flowed; delsp=yes';

    return $message;
  }

  public function wrapMail($text) {
    $htmlText = nl2br($text);
    $htmlText .= $this->signature();

    return '<html><body>' . $htmlText . '</html></body>';
  }

  private function fixHeaderForm(array &$message) {
    //if header From exists and is encoded then recreate it without being cut
    if(array_key_exists('headers', $message) && array_key_exists('From', $message['headers']) &&
      strpos($message['headers']['From'], '=?UTF-8?B') === 0 ) {

      $from = Unicode::mimeHeaderDecode($message['headers']['From']);
      $site_config = \Drupal::service('config.factory')->get('system.site');
      $site_name = $site_config->get('name');

      //fix From header only if does not start with site name
      if(strpos($from, $site_name) !== 0) {
        $fix_from = Unicode::mimeHeaderEncode($site_config->get('name'), false) . ' <' . $message['headers']['Sender'] . '>';
        $fix_from = str_replace("\n", " ", $fix_from);

        $message['headers']['From'] = $fix_from;
      }
    }
  }

  private function signature() {
    $links = [];
    $menu = MenuHelper::getMenu('footer-social-links');
    foreach($menu as $menu_item) {
      $links[$menu_item['title']] = $menu_item['url'];
    }

    $theme_path = drupal_get_path('theme', 'capdel');
    $base_url = Url::fromRoute('<front>', [], ["absolute" => true])->toString();
    $theme_url = $base_url . $theme_path;

    $signature = '<br/><br/>';
    $signature .= '<div>';
    $signature .= '<p>L’équipe Capdel</p>';
    $signature .= '<span>01 41 34 21 00</span><br/>';
    $signature .= '<a href="mailto:votreprojet@capdel.com">votreprojet@capdel.com</a><br/> ';
    $signature .= '  <img src="'.$theme_url.'/logo_dark.png" alt="logo" style="width: 200px" width="200"/>';
    $signature .= '  <br/>';
    $signature .= '  <a href="http://www.capdel.fr"><strong>www.capdel.fr</strong></a>';
    $signature .= '  <br/>';
    $signature .= '  <a href="'.$links['facebook'].'"><img src="'.$theme_url.'/images/facebook-icon.png" alt="facebook" style="margin-right: 10px"/></a>';
    $signature .= '  <a href="'.$links['twitter'].'"><img src="'.$theme_url.'/images/twitter-icon.png" alt="twitter" style="margin-right: 10px"/></a>';
    $signature .= '  <a href="'.$links['linkedin'].'"><img src="'.$theme_url.'/images/linkedin-icon.png" alt="LinkedIn"/></a>';
    $signature .= '</div>';

    return $signature;
  }

}
