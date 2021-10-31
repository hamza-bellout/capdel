<?php

namespace Drupal\capdel_helper\Form;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 20.06.18
 * Time: 12:14
 */
class UserLoginForm
{

  public static function prerender(array $form) {
    $url = new Url('<front>', [], [
        'query' => array_merge(
          ['popup' => 'loginModal'],
          \Drupal::destination()->getAsArray()
        )
      ]
    );
    $form['#action'] = $url->toString();

    return $form;
  }

  public static function redirect(array &$form, FormStateInterface $form_state) {
    if(isset($_COOKIE['login_redirect'])) {
      $url = Url::fromUserInput($_COOKIE['login_redirect']);
    } else {
      $url = new Url('<front>');
    }

    if (isset($_COOKIE['login_redirect'])) {
      unset($_COOKIE['login_redirect']);
      setcookie('login_redirect', null, -1, '/');
    }

    \Drupal::request()->query->remove('destination');
    \Drupal::destination()->set(null);
    $form_state->setRedirectUrl($url);
  }

}
