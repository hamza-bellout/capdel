<?php

namespace Drupal\user_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;


/**
 * Provides a user.
 *
 * @RestResource(
 *   id = "user_rest_resource",
 *   label = @Translation("User POST REST"),
 *   uri_paths = {
 *     "canonical" = "/api/v1.0/custom/user",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1.0/custom/user"
 *   }
 * )
 */
class RestUserResource extends ResourceBase {
  /**
   * Responds to POST requests.
   */
  public function post($mail) {

    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties([
        'mail' => $mail,
    ]);

    if($user != null && $user.mail != null) {
      return new ResourceResponse('Success!',200);
    }
    else {
      return new ResourceResponse('Failed!',400);
    }
  }
}
