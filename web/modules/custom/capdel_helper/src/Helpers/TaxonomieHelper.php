<?php

namespace Drupal\capdel_helper\Helpers;

use Drupal\taxonomy\Entity\Term;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 28.05.18
 * Time: 09:41
 */
class TaxonomieHelper
{
  public static function getTopTaxonomieName($node, $field, $prefferedMenuType = null)
  {
    $topMenuType = self::getTopTaxonomieTerm($node, $field, $prefferedMenuType);
    if(!empty($topMenuType)) {
      return $topMenuType->getName();
    }
  }

  public static function getTopTaxonomieTerm($node, $field, $prefferedMenuType = null)
  {
    $menu_types = $node->{$field}->referencedEntities();
    if(!empty($menu_types)) {
      foreach($menu_types as $menu_type) {
        $top_menu_type = self::getTopTerm($menu_type);
        if(empty($prefferedMenuType)) {
          return $top_menu_type;
        }
        if($top_menu_type->tid->value == $prefferedMenuType) {
          return $top_menu_type;
        }
      }
      return $top_menu_type;
    }
  }

  public static function getTopTerm($term){
    $parents = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($term->id());
    if(!empty($parents)) {
      return self::getTopTerm(reset($parents));
    }

    return $term;
  }

  public static function getTopTerms($vid){
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vid);
    $tids = $query->execute();
    $terms = Term::loadMultiple($tids);

    $topTerms = [];
    foreach ($terms as $term) {
      $topTerm = TaxonomieHelper::getTopTerm($term);
      $tid = $topTerm->tid->value;
      $topTerms[$tid] = $topTerm;
    }

    return $topTerms;
  }

  public static function getTermById($tid) {
    return Term::load($tid);
  }

  public static function getTermsImageAndTitle($terms) {
      if($terms['#items'] !== null) {
          $rawTermItems = ($terms['#items']->getValue());
          $termsArray = [];
          foreach($rawTermItems as $term) {
              $termId = $term['target_id'];
              $termEntity = Term::load($termId);

              //get name of the term
              $termName = $termEntity->name->value;

              //get background image
              $termImage = reset($termEntity->field_tax_menu_type_image->getValue());
              if($termImage) {
                  $termImage = ConfigurateurHelper::getImages($termEntity,'field_tax_menu_type_image', ['taxonomy_block_image']);
              }

              //get term count in Event
              $count = self::getReferencedNodesCountForTerm($termId);

              $termsArray[] = [
                'image' => $termImage[0],
                'id' => $termId,
                'name' => $termName,
                'count' => $count,
              ];
          }
          return $termsArray;
      }
      return [];
  }

  private static function getReferencedNodesCountForTerm($termId, $field='field_event_tax_menu_types', $nodeType='event') {
      $query = \Drupal::entityQuery('node')
        ->condition('type',$nodeType)
        ->condition($field, $termId, 'IN')
        ->count();

      return $query->execute();

  }

    public static function getTermsCount($tid) {
        return self::getReferencedNodesCountForTerm($tid);
    }
}
