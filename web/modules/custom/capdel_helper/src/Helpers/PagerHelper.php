<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 14.08.2018
 * Time: 13:47
 */

namespace Drupal\capdel_helper\Helpers;


use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Class PagerHelper
 *
 * @package Drupal\capdel_helper\Helpers
 */
class PagerHelper
{

    /**
     * @var
     */
    public $items;

    public const VID_ID = [
      'tax_menu_types' => 'evenement_tous=',
      'tax_destination' => 'destination=',
    ];

    /**
     * PagerHelper constructor.
     *
     * @param $items
     */
    public function __construct($items)
    {
        $this->items = $items;
    }

    /**
     * @param $items
     */
    public function setItems($items) {
        if($items !== null) {
            $this->items = $items;
        }
        else {
            $this->items = [];
        }
    }

    /**
     * Helper function to get the assigned landing pages from the given tid.
     * @param $tid
     *
     * @return array
     */
    public function getLandingPageDataForTid($tid) : array {
        if($tid == null) {
            return [
              'data' => [],
              'count' => 0
            ];
        }
        $termVid = Term::load($tid)->bundle();
        $termSearchUrl = self::VID_ID[$termVid];

        $query = \Drupal::entityQuery('node')
          ->condition('type','landing_page')
          ->condition('status',1)
          ->condition('field_lp_tax_ref',$tid, '=')
          ->sort('field_lp_order','ASC');

        $relatedNids = $query->execute();
        $resultDataArray = [];
        $globalCount = 0;
        if(\count($relatedNids) > 0) {
            $nodesArray = Node::loadMultiple($relatedNids);
            foreach ($nodesArray as $node) {
                $url = \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$node->id())
                  .'?'
                  .$termSearchUrl
                  .$tid;

                $count = $node->get('field_lp_ref_attached_event');

                if($count) {
                    $count = \count($node->get('field_lp_ref_attached_event')->getValue());
                    $globalCount += $count;
                }

                $resultDataArray[] = [
                  'href' => $url,
                ];
            }
        }

        return [
          'data' => $resultDataArray,
          'count' => $globalCount
          ];
    }

    /**
     * @param $landingPagesArray - array of lp urls to be added to the pager
     * items
     * @param $currentNumber - current page number of the pager
     * which needs to be overwritten
     *
     * @return array|bool
     */
    public function addLandingPageArrayToItems($landingPagesArray, $currentNumber) {
        $currentIncrement = \count($landingPagesArray);
        $prevElement = false;
        $resultingPagerItemsArray = false;
        $additionalIncrement = 0;

        if($this->items['pages'] === null) {
            $additionalIncrement++;
        }

        if(\count($landingPagesArray) > 0) {
            switch ($currentIncrement) {
                case 3:
                    if($currentNumber >= 1 && $currentNumber <= 2) {
                        $resultingPagerItemsArray = $this->addThePagesToTheArray($landingPagesArray);
                        // add all elements to the array
                        if($currentNumber == 1) {
                            //prev, first
                            $prevElement = [
                              'href' => $landingPagesArray[0]['href'],
                              'text' => '«',
                            ];
                        }
                    }
                    else {
                        // even if we do not display the pages,
                        // we still need to increment the index of the pager
                        $resultingPagerItemsArray = $this->reasignThePagerIndex($currentIncrement);
                    }
                    break;
                case 2:
                    if($currentNumber >= 1 && $currentNumber <= 3) {
                        $resultingPagerItemsArray = $this->addThePagesToTheArray($landingPagesArray);
                        // add all elements to the array
                        if($currentNumber == 1) {
                            //prev, first
                            $prevElement = [
                              'href' => $landingPagesArray[0]['href'],
                              'text' => '«',
                            ];
                        }
                    }
                    else {
                        // even if we do not display the pages,
                        // we still need to increment the index of the pager
                        $resultingPagerItemsArray = $this->reasignThePagerIndex($currentIncrement);
                    }
                    break;
                case 1:
                    if($currentNumber >= 1 && $currentNumber <= 4) {
                        $resultingPagerItemsArray = $this->addThePagesToTheArray($landingPagesArray);
                        // add all elements to the array
                        if($currentNumber == 1) {
                            //prev, first
                            $prevElement = [
                              'href' => $landingPagesArray[0]['href'],
                              'text' => '«',
                            ];
                        }
                    }
                    else {
                        // even if we do not display the pages,
                        // we still need to increment the index of the pager
                        $resultingPagerItemsArray = $this->reasignThePagerIndex($currentIncrement);
                    }
                    break;
                default:
                    break;
            }

            $firstElement = [
              'href' => $landingPagesArray[0]['href'],
              'text' => '« First',
            ];

            return [
              'data' => [
                'pages' => $resultingPagerItemsArray,
                'first' => $firstElement,
                'prev' => $prevElement
                ],
              'currentIncrement' => $currentIncrement + $additionalIncrement
              ];
        }
        return false;
    }

    /**
     * @param $currentIncrement
     *
     * @return array
     */
    private function reasignThePagerIndex($currentIncrement) {
        $returnArray = [];
        foreach($this->items['pages'] as $key => $value) {
            $returnArray[$key + $currentIncrement] = $value;
        }
        return $returnArray;
    }

    /**
     * @param $landingPagesArray
     * @param array $unsetArray
     *
     * @return array
     */
    private function addThePagesToTheArray($landingPagesArray,$unsetArray=[]) {
        if(\count($unsetArray) > 0) {
            foreach($unsetArray as $key) {
                unset($landingPagesArray[$key]);
            }
        }

        if($this->items['pages'] === null) {
            // FIXME: hacky way to generate current page, as if there is no pages
            // the pager is not generated at all.
            $this->items['pages'] = [
              0 => [
                'href' => '/'
              ]
            ];
        }

        $resultArray =  array_merge($landingPagesArray,$this->items['pages']);
        array_unshift($resultArray,'');
        unset($resultArray[0]);

        return $resultArray;
    }

}