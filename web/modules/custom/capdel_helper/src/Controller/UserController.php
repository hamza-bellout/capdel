<?php
namespace Drupal\capdel_helper\Controller;
use Drupal\user\Entity\User;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 19.06.18
 * Time: 13:04
 */
class UserController extends \Drupal\user\Controller\UserController
{
  public function resetPassLogin($uid, $timestamp, $hash) {
    $response = parent::resetPassLogin($uid, $timestamp, $hash);

    if(strpos($response->getTargetUrl(), 'pass-reset-token') !== false){
      $query = [
        'popup' => 'newPasswordModal',
      ];

      if(array_key_exists('op', $_REQUEST) && $_REQUEST['op'] = 'create'){
        $query['popup'] = 'createPasswordModal';
      }

      //when password reset token is correct
      return $this->redirect('<front>', [], ['query' => $query]);

    } else if(strpos($response->getTargetUrl(), '/user/password') !== false) {
      //when password reset token was already used
      return $this->redirect('<front>', [], ['query' => [
        'popup' => 'rememberModal',
        'error' => 'tokenInvalid'
      ]]);
    }

    return $response;
  }

  public function changePassword() {
    $newPassword = \Drupal::request()->request->get('newPassword');

    $user = User::load(\Drupal::currentUser()->id());
    $user->setPassword($newPassword);
    $user->save();

    $query = [
      'popup' => 'passwordChanged',
    ];

    if(array_key_exists('op', $_REQUEST) && $_REQUEST['op'] = 'create'){
      $query['popup'] = 'passwordCreated';
    }

    return $this->redirect('<front>', [], ['query' => $query]);
  }
}
