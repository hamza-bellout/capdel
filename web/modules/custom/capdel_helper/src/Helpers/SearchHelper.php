<?php

namespace Drupal\capdel_helper\Helpers;
use Drupal\node\Entity\Node;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 28.05.18
 * Time: 09:41
 */
class SearchHelper
{

  public static function resolveSearchResultRow($row) {
    $item = $row->_item;
    if($item) {
      $node = $item->getOriginalObject()->getValue();
      if($node->bundle() == 'event_subpackage') {
        return self::getEventFromSubpackage($node);
      } else {
        return $node;
      }
    }
  }

    public static function resolveFavResultRow($row) {
        if(property_exists($row,'nid') && $row->nid) {
            return Node::load($row->nid);
        }
    }

  private static function getEventFromSubpackage($node) {
      $query = \Drupal::entityQuery('node')
        ->condition('field_event_ref_variant', $node->nid->value, '=');
      $nids = $query->execute();

      if(!empty($nids)) {
        $events = Node::loadMultiple(array_values($nids));
        return reset($events);
      }
  }

  public static function extractCoordinates(array $rows) {
    if(empty($rows)){
      return [];
    } else {
      $row = self::resolveSearchResultRow($rows[0]['#row']);
      switch ($row->bundle()) {
        case 'event' : return SearchHelper::extractCoordinatesFromEvents($rows);
        case 'conf_lieu' : return SearchHelper::extractCoordinatesFromConfLieu($rows);
        default : return [];
      }
    }
  }

  public static function extractCoordinatesFromEvents(array $rows) {
    $coordinates = [];
    foreach($rows as $row) {
      $event = self::resolveSearchResultRow($row['#row']);
      $eventInfo = EventHelper::getEventInfo($event);
      if (count($eventInfo['coordinates'])) {
        $coordinates = array_merge($coordinates, $eventInfo['coordinates']);
      }
    }
    return $coordinates;
  }

  public static function extractCoordinatesFromConfLieu(array $rows) {
    $coordinates = [];
    foreach($rows as $row) {
      $confLieu = self::resolveSearchResultRow($row['#row']);
      $confLieuInfo = ConfigurateurHelper::getEntityInfo($confLieu);

      if (!empty($confLieuInfo['coordinates'])) {
        $coordinates[] = $confLieuInfo['coordinates'];
      }
    }
    return $coordinates;
  }

  public static function getSearchAndLPresults(array $parameters) {

      $destinationTid = $parameters['destination'];
      $categoryTid = $parameters['evenement_tous'];

      $text = $parameters['texte'];
      $evenementPrix = $parameters['evenement_prix'];

      // case when there are multiple taxonomies attached.
      // then just display the normal search header as there is no LP with
      // multiple taxonomies selected (for now).
      if(($destinationTid !== 'All' && $categoryTid !== 'All') ||
        $evenementPrix !== '' || $text !== ''
      ) {
          return false;
      }

      if($categoryTid === 'All' && $destinationTid !== 'All') {
          $categoryTid = $destinationTid;
      }

      //Get search results for menu_all field
      $searchResultsCount = (int)LandingPageHelper::countTerms($categoryTid);

      //If menu is empty, then try with destination
      if($searchResultsCount == 0) {
          $searchResultsCount = (int)LandingPageHelper::countTerms($categoryTid,'field_event_sub_tax_destination');
      }

      return $searchResultsCount;
  }
}
