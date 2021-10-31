<?php

namespace Drupal\capdel_helper\Helpers;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class BreadcrumbHelper
{
  public static function buildNodeBreadcrumb(Breadcrumb &$breadcrumb, RouteMatchInterface $route_match, $node)
  {
    if(!$node instanceof Node){
      return;
    }

    if ($node->bundle() == 'event') {
      self::buildEventBreadcrumb($breadcrumb, $route_match, $node);
    }
  }

  private static function buildEventBreadcrumb(Breadcrumb &$breadcrumb, RouteMatchInterface $route_match, Node $node) {
    $breadcrumb->getLinks();
    $prefferedMenuType = null;

    if(array_key_exists('Drupal_visitor_event_type', $_COOKIE)){
      $prefferedMenuType = $_COOKIE['Drupal_visitor_event_type'];
    }

    $menuType = TaxonomieHelper::getTopTaxonomieTerm($node, 'field_event_tax_menu_types', $prefferedMenuType);

    $links = [];
    $links[] = Link::createFromRoute(t('Home'), '<front>');

    if($menuType != null) {
      $url = Url::fromUserInput('/search?evenement_tous=' . $menuType->tid->value);
      $links[] = new Link($menuType->getName(), $url);
    }

    $links[] = Link::createFromRoute($node->getTitle(), '<none>');

    $myBreadcrumb = new Breadcrumb();
    $myBreadcrumb->addCacheableDependency($breadcrumb);
    $myBreadcrumb->mergeCacheMaxAge(0);
    $myBreadcrumb->setLinks($links);

    $breadcrumb = $myBreadcrumb;
  }
}
