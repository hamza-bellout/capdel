<?php

namespace Drupal\capdel_helper\Helpers;

use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\taxonomy\Entity\Term;

/**
 * Class LandingPageHelper
 *
 * @package Drupal\capdel_helper\Helpers
 */
class LandingPageHelper
{

    /**
     * Default parameter for pager counter
     */
    public const PAGER_COUNT = 10;

    public const VID_ID = [
      'tax_menu_types' => 'evenement_tous=',
      'tax_destination' => 'destination=',
    ];

    /**
     * @param \Drupal\node\Entity\Node $node
     *
     * @return array|bool
     */public static function getPagerItems(Node $node) {
      $orderNo = 1;
      $referencedTaxonomyId = false;
      $returnArray = [];


      if($node->get('field_lp_order') != null) {
          $orderNo = $node->get('field_lp_order')->getValue();
      }

      if($node->get('field_lp_tax_ref') != null) {
          $referencedTaxonomyId = $node->get('field_lp_tax_ref')->getValue()[0]['target_id'];
      }

      if($referencedTaxonomyId) {
          $termVid = Term::load($referencedTaxonomyId)->bundle();
          $termSearchUrl = self::VID_ID[$termVid];

          $query = \Drupal::entityQuery('node')
            ->condition('type','landing_page')
            ->condition('status',1)
            ->condition('field_lp_tax_ref',$referencedTaxonomyId, '=')
            ->sort('field_lp_order','ASC');

          $relatedNids = $query->execute();
          $index = 1;

          if(\count($relatedNids) > 0) {
            foreach ($relatedNids as $nid) {
                $returnArray['pages'][$index]['href'] = \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$nid)
                  .'?'
                  .$termSearchUrl
                  .$referencedTaxonomyId;

                if($nid == $node->id()) {
                    $returnArray['current'] = $index;
                }
                $index++;
            }
          }

          //This is the place to add search result page
          $searchResultsCount = (int)self::countTerms($referencedTaxonomyId,'field_event_tax_menu_types',true);

          if($searchResultsCount == 0) {
              $searchResultsCount = (int)self::countTerms($referencedTaxonomyId,'field_event_sub_tax_destination',true);
          }

          // e.g. 46 -> 5 pages -> 4 pages (0 - 4)
          $pages = (int)(ceil($searchResultsCount / self::PAGER_COUNT)) - 1 ;
          //render more and last button
          $returnArray['last']['href'] = "/search?".$termSearchUrl.$referencedTaxonomyId.'&page='.$pages;
          $pageCounter = 0;
          do {
              if($pages >= $pageCounter) {
                  $returnArray['pages'][$index]['href'] = "/search?".$termSearchUrl.$referencedTaxonomyId.'&page='.$pageCounter;
              }
              $index++;
              $pageCounter++;
          } while ($index <= 9);

          if($returnArray['current'] > 1) {
              $prevIndex = $returnArray['current']-1;
              $returnArray['previous']['href'] = $returnArray['pages'][$prevIndex]['href'];
              $returnArray['first']['href'] = $returnArray['pages'][1]['href'];
          }

          if($returnArray['current'] < \count($returnArray['pages'])) {
              $nextIndex = $returnArray['current']+1;
              $returnArray['next']['href'] = $returnArray['pages'][$nextIndex]['href'];
          }

          return $returnArray;
      }

      return false;
  }

    /**
     * Helper function to get the search results using Search API to display
     * in the header.
     *
     * @param $termId
     * @param string $field
     *
     * @return mixed
     */
    public static function countTerms(
      $termId,
      $field = 'field_event_tax_menu_types',
      $raw = false
    ) {
        //load normal query nodes
        $query = Index::load('events_subpackages_index')->query();
        $query->addCondition('status', 1)
          ->addCondition($field, $termId);
        $data = $query->execute();

        $count = $data->getResultCount();

        //no results found, no need to process LPs
        if($count == 0) {
            return 0;
        }

        //return only search results, without LP content
        if($raw) {
            return $count;
        }

        //load LP page count
        $queryLP = \Drupal::entityQuery('node')
          ->condition('type', 'landing_page')
          ->condition('status', 1)
          ->condition('field_lp_tax_ref', $termId, '=')
          ->count();

        $landingPageCount = $queryLP->execute();
        //add additional nodes from LP to count results
        $count += $landingPageCount * self::PAGER_COUNT;
        return $count;
    }


    /**
     * This function validates the landing page forms (edit page).
     * Disallowing to save the LP with less than 10 content entities and the
     * same order number as the previous LPs for the same node.
     * @param $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     */
    public static function validateLandingPageForms (&$form, \Drupal\Core\Form\FormStateInterface $form_state) {
      $referencedEventsArray = $form_state->getValue('field_lp_ref_attached_event');
      if ($referencedEventsArray) {
        $isArrayFull = self::isAutocompleteReferenceFieldFull($referencedEventsArray);
        if(!$isArrayFull) {
            $form_state->setErrorByName('field_lp_ref_attached_event', t('Please fill in 10 elements in Content Reference section'));
        }
      }

      $orderNumber = $form_state->getValue('field_lp_order');
      $selectedTID = $form_state->getValue('field_lp_tax_ref');
      $nodeId = $form_state->getFormObject()->getEntity()->get('nid')->getValue()[0]['value'];

        if(isset($selectedTID[0]) && isset($selectedTID[0]['target_id'])) {
            $selectedTID = $selectedTID[0]['target_id'];

            if(isset($orderNumber[0]) && isset($orderNumber[0]['value'])) {
                $orderValue = $orderNumber[0]['value'];
                // if node exists, edit action
                if($nodeId !== null) {
                    $query = \Drupal::entityQuery('node')
                      ->condition('type','landing_page')
                      ->condition('field_lp_tax_ref', $selectedTID, 'IN')
                      ->condition('field_lp_order',$orderValue)
                      ->condition('nid',$nodeId,'!=')
                      ->count();
                // if node does not exists, create action
                } else {
                    $query = \Drupal::entityQuery('node')
                      ->condition('type','landing_page')
                      ->condition('field_lp_tax_ref', $selectedTID, 'IN')
                      ->condition('field_lp_order',$orderValue)
                      ->count();
                }
                $result = $query->execute();
                if($result > 0) {
                    $form_state->setErrorByName('field_lp_order', t('This order number is already taken for this taxonomy term'));
                }
            }

        }
  }

    /**
     * Helper function to check the autocomplete reference.
     * The field is empty when the target_id property is null.
     * @param $referenceArray
     *
     * @return bool
     */
  private static function isAutocompleteReferenceFieldFull ($referenceArray) : bool {
      $returnCheckbox = true;
      foreach ($referenceArray as $item) {
          if($item['target_id'] == null) {
              $returnCheckbox = false;
          }
      }
      return $returnCheckbox;
  }

  public static function addReferencedTaxonomieRequestParam(Node $node) {
    if(!self::hasReferencedTaxonomieRequestParam()) {
      $taxonomie = self::getLandingPageReferencedTaxonomy($node);

      if ($taxonomie != null) {
        $referencedTaxonomyId = $taxonomie->id();
        $termSearchUrl = trim(self::VID_ID[$taxonomie->bundle()], '=');
        \Drupal::request()->query->add([$termSearchUrl => $referencedTaxonomyId]);
      }
    }
  }

  public static function hasReferencedTaxonomieRequestParam(){
    foreach(self::VID_ID as $vid_id){
      $cleanVidId = trim($vid_id, '=');
      if(\Drupal::request()->query->get($cleanVidId) != null){
        return true;
      }
    }
    return false;
  }

  private static function getLandingPageReferencedTaxonomy(Node $node) : Term
  {
    //if there is tax reference found
    if ($node->get('field_lp_tax_ref') != null) {
      $referencedTaxonomyId = $node->get('field_lp_tax_ref')->getValue()[0]['target_id'];
      return Term::load($referencedTaxonomyId);
    }
  }

}
