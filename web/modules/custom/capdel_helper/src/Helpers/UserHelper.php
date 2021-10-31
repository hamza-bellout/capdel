<?php

namespace Drupal\capdel_helper\Helpers;

class UserHelper
{
  public static function getUserProfile() {
    $messenger = \Drupal::messenger();
    $messenger->deleteAll();

    $user = \Drupal::currentUser();
    $user_entity = \Drupal::entityTypeManager() ->getStorage('user') ->load($user->id());
    $formObject = \Drupal::entityTypeManager()
      ->getFormObject('user', 'default')
      ->setEntity($user_entity);

    $form = \Drupal::formBuilder()->getForm($formObject);

    $form['account']['mail']['#weight'] = -5;
    $form['account']['current_pass']['#weight'] = 0;

    $form['account']['name']['#access'] = true;
    $form['account']['name']['#type'] = 'hidden';

    $form['#attributes']['onsubmit'] = 'return validateProfileEdit()';

    unset($form['account']['roles']);
    unset($form['language']);
    unset($form['account']['status']);
    unset($form['account']['notify']);
    unset($form['timezone']);
    unset($form['contact']);

    return $form;
  }


  public static function getUserInfo() {
    $user = \Drupal::currentUser();
    $user_entity = \Drupal::entityTypeManager() ->getStorage('user') ->load($user->id());
    $userInfo = array(
      'civility' => $user_entity->field_civility->value,
      'last_name' => $user_entity->field_last_name->value,
    );
    return $userInfo;
  }

  public static function getUserID() {
    $account = \Drupal::currentUser();
    return $account->id();
  }

}
