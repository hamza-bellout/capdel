<?php

namespace Drupal\capdel_helper\Form;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 20.06.18
 * Time: 12:14
 */
class UserEditForm
{

  public static function redirect(array &$form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();

    //on profile edit current user has id
    if($current_user->id()) {
      $url = Url::fromUserInput('/profileinfo');
      $form_state->setRedirectUrl($url);
    } else {
      //on account creation redirect to the last page
      $destination = \Drupal::request()->request->get('destination');
      $parse_destination = parse_url($destination);

      $query = [];
      parse_str($parse_destination['query'], $query);
      $query['popup'] = 'confirmCreate';

      $url = $parse_destination['path'] . '?' . http_build_query($query);

      //there was an issue with second form submit when $form_state->setRedirectUrl($url) approach was used
      $response = new TrustedRedirectResponse($url);
      $response->send();
      die;
    }
  }

}
