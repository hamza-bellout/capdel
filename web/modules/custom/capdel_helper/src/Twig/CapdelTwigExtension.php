<?php

namespace Drupal\capdel_helper\Twig;

use Drupal\capdel_helper\Helpers\ConfigurateurHelper;
use Drupal\capdel_helper\Helpers\ConfigurateurPlaceHelper;
use Drupal\capdel_helper\Helpers\EventHelper;
use Drupal\capdel_helper\Helpers\FormHelper;
use Drupal\capdel_helper\Helpers\LandingPageHelper;
use Drupal\capdel_helper\Helpers\MenuHelper;
use Drupal\capdel_helper\Helpers\PackageHelper;
use Drupal\capdel_helper\Helpers\PartnerHelper;
use Drupal\capdel_helper\Helpers\RequestHelper;
use Drupal\capdel_helper\Helpers\SearchHelper;
use Drupal\capdel_helper\Helpers\TaxonomieHelper;
use Drupal\capdel_helper\Helpers\UserHelper;
use Drupal\node\Entity\Node;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 30.05.18
 * Time: 11:31
 */
class CapdelTwigExtension extends \Twig_Extension {

  /**
   * In this function we can declare the extension function
   */
  public function getFunctions() {
    return [
      //ConfigurateurHelper
      new \Twig_SimpleFunction('getConfigurateurEntityInfo', array($this, 'getConfigurateurEntityInfo')),
      new \Twig_SimpleFunction('removeValueFromHeader', array($this, 'removeValueFromHeader')),
      new \Twig_SimpleFunction('getNodeAlias', array($this, 'getNodeAlias')),

      //ConfiguratorPlaceHelper
      new \Twig_SimpleFunction('getConfPlaceInfo', array($this, 'getConfPlaceInfo')),

      //EventHelper
      new \Twig_SimpleFunction('getEventInfo', array($this, 'getEventInfo')),
      new \Twig_SimpleFunction('getFavLink', array($this, 'getFavLink')),
      new \Twig_SimpleFunction('getPagerItems', array($this, 'getPagerItems')),

      //FormHelper
      new \Twig_SimpleFunction('getUserRegistrationForm', array($this, 'getUserRegistrationForm')),
      new \Twig_SimpleFunction('getEventReservationForm', array($this, 'getEventReservationForm')),
      new \Twig_SimpleFunction('getPasswordResetForm', array($this, 'getPasswordResetForm')),

      //MenuHelper
      new \Twig_SimpleFunction('getMenu', array($this, 'getMenu')),

      //PackageHelper
      new \Twig_SimpleFunction('getPackageInfo', array($this, 'getPackageInfo')),

      //PartnerHelper
      new \Twig_SimpleFunction('getPartnerInfo', array($this, 'getPartnerInfo')),

      //RequestHelper
      new \Twig_SimpleFunction('getQueryParams', array($this, 'getQueryParams')),
      new \Twig_SimpleFunction('isCategoryListPage', array($this, 'isCategoryListPage')),
      new \Twig_SimpleFunction('getRequestUri', array($this, 'getRequestUri')),
      new \Twig_SimpleFunction('getPaxParticipantsCookie', array($this, 'getPaxParticipantsCookie')),

      //SearchHelper
      new \Twig_SimpleFunction('resolveSearchResultRow', array($this, 'resolveSearchResultRow')),
      new \Twig_SimpleFunction('resolveFavResultRow', array($this, 'resolveFavResultRow')),
      new \Twig_SimpleFunction('extractCoordinatesFromSearch', array($this, 'extractCoordinatesFromSearch')),
      new \Twig_SimpleFunction('getSearchAndLPresults', array($this, 'getSearchAndLPresults')),

      //TaxonomieHelper
      new \Twig_SimpleFunction('getTermById', array($this, 'getTermById')),
      new \Twig_SimpleFunction('getTermsImageAndTitle', array($this, 'getTermsImageAndTitle')),
      new \Twig_SimpleFunction('getTermsCount', array($this, 'getTermsCount')),

      //UserHelper
      new \Twig_SimpleFunction('getUserProfile', array($this, 'getUserProfile')),
      new \Twig_SimpleFunction('getUserInfo', array($this, 'getUserInfo')),
      new \Twig_SimpleFunction('getUserID', array($this, 'getUserID')),

      new \Twig_SimpleFunction('getMessages', array($this, 'getMessages')),

      //LandingPageHelper
      new \Twig_SimpleFunction('countTerms', array($this, 'countTerms')),
    ];
  }

  public function getEventInfo(Node $node, $preferableMenuType, $addRequestParams) {
    return EventHelper::getEventInfo($node, $preferableMenuType, $addRequestParams);
  }

    public function getFavLink(Node $node) {
        return EventHelper::getFavLink($node);
    }

    public function getPagerItems(Node $node) {
        return LandingPageHelper::getPagerItems($node);
    }

  public function getPackageInfo(Node $node) {
    return PackageHelper::getPackageInfo($node);
  }

  public function getPartnerInfo(Node $node) {
    return PartnerHelper::getPartnerInfo($node);
  }

  public function getUserProfile() {
    return UserHelper::getUserProfile();
  }


  public function getUserInfo() {
    return UserHelper::getUserInfo();
  }

  public function resolveSearchResultRow($row) {
    return SearchHelper::resolveSearchResultRow($row);
  }

    public function resolveFavResultRow($row) {
        return SearchHelper::resolveFavResultRow($row);
    }

  public function extractCoordinatesFromSearch($rows) {
    return SearchHelper::extractCoordinates($rows);
  }

  public function getSearchAndLPresults($params) {
      return SearchHelper::getSearchAndLPresults($params);
  }

  public function getConfigurateurEntityInfo($node, $referencedNodes=null) {
    return ConfigurateurHelper::getEntityInfo($node,$referencedNodes);
  }

  public function getConfPlaceInfo($node) : array {
      return ConfigurateurPlaceHelper::getConfPlaceInfo($node);
  }

  public function removeValueFromHeader($header, $value) {
      return ConfigurateurHelper::removeValueFromHeader($header, $value);
  }

  public function getNodeAlias($id) {
      return ConfigurateurHelper::getNodeAlias($id);
  }


  public function getMenu($name, $access=true) {
    return MenuHelper::getMenu($name, $access);
  }

  public function getQueryParams() {
    return RequestHelper::getQueryParams();
  }

  public function getUserRegistrationForm() {
    return FormHelper::getUserRegistrationForm();
  }

  public function getEventReservationForm() {
    return FormHelper::getEventReservationForm();
  }

  public function getPasswordResetForm() {
    return FormHelper::getPasswordResetForm();
  }

  public function getUserID() {
    return UserHelper::getUserID();
  }

  public function isCategoryListPage() {
    return RequestHelper::isCategoryListPage();
  }

  public function getTermById($tid) {
    return TaxonomieHelper::getTermById($tid);
  }

  public function getTermsCount($tid) {
    return TaxonomieHelper::getTermsCount($tid);
  }

  public function getTermsImageAndTitle($terms) {
      return TaxonomieHelper::getTermsImageAndTitle($terms);
  }

  public function getMessages($type) {
    $messages = \Drupal::messenger()->messagesByType($type);
    return $messages;
  }

  public function getRequestUri() {
    return RequestHelper::getRequestUri();
  }

  public function getPaxParticipantsCookie() {
    return RequestHelper::getPaxParticipantsCookie();
  }

  public function countTerms($tid,$field='field_event_tax_menu_types') {
      return LandingPageHelper::countTerms($tid,$field);
  }
}
