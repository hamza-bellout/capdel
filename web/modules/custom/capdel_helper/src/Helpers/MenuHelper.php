<?php

namespace Drupal\capdel_helper\Helpers;
use Drupal\Core\Menu\InaccessibleMenuLink;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Url;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 28.05.18
 * Time: 09:41
 */
class MenuHelper
{


    /**
     * @param $name
     * @param bool $access Defines if we skip the access check
     *
     * @return array
     */
  public static function getMenu($name, $access=true)
  {
    $menu_parameters = new \Drupal\Core\Menu\MenuTreeParameters();
    $menu_parameters->setMaxDepth(3);

    $menu_tree_service = \Drupal::service('menu.link_tree');
    $tree = $menu_tree_service->load($name, $menu_parameters);

    if($access) {
        $manipulators = [
          ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
          ['callable' => 'menu.default_tree_manipulators:checkAccess'],
          ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
        ];
    }
    else {
        $manipulators = [
          ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
        ];
    }

    $tree = $menu_tree_service->transform($tree, $manipulators);

    return self::resolveMenuTree($tree);
  }

  private static function resolveMenuTree(array $tree)
  {
    $menu = [];
    foreach($tree as $menuLink) {
      if($menuLink->link instanceof InaccessibleMenuLink) {
        continue;
      }
      if(!$menuLink->link->isEnabled()) {
        continue;
      }
      $item = self::resolveMenuLink($menuLink);
      if (!empty($menuLink->subtree)) {
        $item['children'] = self::resolveMenuTree($menuLink->subtree);
      }
      $menu[] = $item;
    }
    return $menu;
  }

  private static function resolveMenuLink(MenuLinkTreeElement $menuLink)
  {
    $url = $menuLink->link->getUrlObject();
    $attributes = self::parseDescription($menuLink);

    $link = array_merge([
      'title' => $menuLink->link->getTitle(),
      'url' => $url->toString() !== '' ? $url->toString() : '#',
    ], $attributes);

    if (!empty($menuLink->link->getOptions())) {
      $options = $menuLink->link->getOptions();
      if(array_key_exists('attributes', $options)) {
        if(array_key_exists('target', $options['attributes'])){
          $link['target'] = $options['attributes']['target'];
        }
        if(array_key_exists('class', $options['attributes'])){
          $link['class'] = implode(" ", $options['attributes']['class']);
        }
      }
    }

    return $link;
  }

  private static function parseDescription(MenuLinkTreeElement $menuLink)
  {
    //default values
    $width = 4;
    $cols = 1;
    $key = $menuLink->link->getPluginId();

    $attributes = explode(';', $menuLink->link->getDescription());
    foreach($attributes as $attribute){
      if(strpos($attribute,':') !== false) {
        list($_key, $_value) = explode(':', $attribute);
        if (!empty($_key) && !empty($_value)) {
          switch ($_key) {
            case 'width':
              $width = $_value;
              break;
            case 'cols' :
              $cols = $_value;
              break;
            case 'key' :
              $key = $_value;
              break;
          }
        }
      }
    }

    return [
      'width' => $width,
      'cols' => $cols,
      'key' => $key,
    ];
  }

}
