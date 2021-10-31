<?php

namespace Drupal\capdel_helper\Helpers;

class RequestHelper
{
  public static function isCategoryListPage() {
    //detect entry to category type search results from menu
    if (strpos($_SERVER['REQUEST_URI'], '/search?') !== false && !empty($_GET['evenement_tous'])
      && $_GET['evenement_tous'] != 'All') {
      return true;
    }
    return false;
  }

  public static function isLandingPage(){
    $node = \Drupal::routeMatch()->getParameter('node');
    if(isset($node) &&  $node->getType() == "landing_page") {
      return true;
    }
    return false;
  }

  public static function isAdminUrl() {
    if (strpos($_SERVER['REQUEST_URI'], '/admin') !== false) {
      return true;
    }
    return false;
  }

  public static function getRequestUri() {
    return \Drupal::request()->getRequestUri();
  }

  public static function getPaxParticipantsCookie() {
    if(array_key_exists('Drupal_visitor_pax_participants', $_COOKIE)){
      return $_COOKIE['Drupal_visitor_pax_participants'];
    }
  }

  public static function getQueryParams() {
    $query['raw'] = $_GET;

    $queryParams = $_GET;
    //todo: make unset as an option parameter
    unset($queryParams['q']);

    $queryParams = implode('&',array_map(
      function ($v, $k) { return sprintf("%s=%s", $k, $v); },
      $queryParams,
      array_keys($queryParams)
    ));

    $query['params'] = '?'.$queryParams;

    return $query;
  }

}
