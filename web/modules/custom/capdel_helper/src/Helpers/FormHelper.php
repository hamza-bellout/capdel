<?php

namespace Drupal\capdel_helper\Helpers;

class FormHelper
{
  public static function getUserRegistrationForm() {
    $entity = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->create([]);

    $formObject = \Drupal::entityTypeManager()
      ->getFormObject('user', 'register')
      ->setEntity($entity);

    $form = \Drupal::formBuilder()->getForm($formObject);
    unset($form['account']['mail']['#description']);
    unset($form['account']['pass']['#description']);
    unset($form['account']['name']['#description']);
    unset($form['account']['roles']);
    unset($form['language']);
    unset($form['account']['status']);
    unset($form['account']['notify']);
    unset($form['timezone']);
    unset($form['contact']);
    $form['account']['name']['#title']= 'Confirm email';
    return $form;
  }

  public static function getPasswordResetForm() {
    $form = \Drupal::formBuilder()->getForm('\Drupal\user\Form\UserPasswordForm');
    unset($form['mail']['#markup']);
    return $form;
  }

  public static function getEventReservationForm() {
      return \Drupal::formBuilder()->getForm('Drupal\capdel_helper\Form\EventReservationForm');
  }

}
