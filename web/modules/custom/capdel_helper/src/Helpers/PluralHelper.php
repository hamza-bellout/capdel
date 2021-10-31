<?php

namespace Drupal\capdel_helper\Helpers;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 22.06.18
 * Time: 11:45
 */

namespace Drupal\capdel_helper\Helpers;

class PluralHelper
{
  const QUANTITY_ONE = 'one';
  const QUANTITY_MANY = 'many';

  private static $dictionary = [
    'demi-journee' =>[
      self::QUANTITY_ONE => 'demi-journee',
      self::QUANTITY_MANY => 'demi-journées'
    ],
    'heure' =>[
      self::QUANTITY_ONE => 'heure',
      self::QUANTITY_MANY => 'heures'
    ],
    'journée' =>[
      self::QUANTITY_ONE => 'journée',
      self::QUANTITY_MANY => 'journées'
    ],
    'soiree' =>[
      self::QUANTITY_ONE => 'soirée',
      self::QUANTITY_MANY => 'soirées'
    ],
  ];

  private static $replace = [
    'journee' => 'journée'
  ];

  public static function parse($string, $qty) {
    $text = self::replace($string);
    if(array_key_exists($text, self::$dictionary)){
      $quantity = self::quantity($qty);
      if(array_key_exists($quantity, self::$dictionary[$text])){
        return self::$dictionary[$text][$quantity];
      }
    }
    return $string;
  }

  private static function replace($string) {
    if(array_key_exists($string, self::$replace)){
      return self::$replace[$string];
    }
    return $string;
  }

  private static function quantity($quantity) {
    if ($quantity == 0) {
      return self::QUANTITY_MANY;
    } else if ($quantity == 1) {
      return self::QUANTITY_ONE;
    } else {
      return self::QUANTITY_MANY;
    }
  }
}
