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
class UserPasswordForm
{
    public static function validate(array &$form, FormStateInterface $form_state) {
      if ($form_state->hasAnyErrors()) {
        $url = new Url('<front>', [], ['query' => [
          'popup' => 'rememberModal',
          'error' => 'emailError'
        ]]);

        $response = new TrustedRedirectResponse($url->toString());
        $response->send();
        die;
      }
    }

  public static function redirect(array &$form, FormStateInterface $form_state) {
    if (isset($_COOKIE['login_redirect'])) {
      unset($_COOKIE['login_redirect']);
      setcookie('login_redirect', null, -1, '/');
    }

    $url = new Url('<front>', [], ['query' => [
      'popup' => 'passwordResetRequested',
    ]]);

    \Drupal::request()->query->remove('destination');
    \Drupal::destination()->set(null);
    $form_state->setRedirectUrl($url);
  }
}
