<?php

namespace Drupal\capdel_helper\Helpers;

use Drupal\node\Entity\Node;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 28.05.18
 * Time: 09:41
 */
class PartnerHelper
{
  public static function getPartnerInfo(Node $node) {
    $partner = [];

    $partner['images'] = ImagesHelper::getImages($node, 'field_partner_logo');

    return $partner;
  }
}
