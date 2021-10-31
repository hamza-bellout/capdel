<?php

namespace Drupal\capdel_helper\Helpers;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class NodeHelper
{
  public static function getNodeFromUrl($url) {
    $parsedUrl = parse_url($url);

    $alias = \Drupal::service('path.alias_manager')->getPathByAlias($parsedUrl['path']);
    $urlParams = Url::fromUri('internal:'.$alias)->getRouteParameters();

    return Node::load($urlParams['node']);
  }
}
