<?php

namespace Drupal\custom_rest\Plugin\rest\resource;

use Drupal\capdel_helper\Plugin\xmlrpc\OdooIntegrationPlugin;
use Drupal\custom_rest\ReservationResource;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;


/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "rest_reservation_resource",
 *   label = @Translation("Reservation POST REST"),
 *   serialization_class = "\Drupal\custom_rest\ReservationResource",
 *   uri_paths = {
 *     "canonical" = "/api/v1.0/custom/reservation",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1.0/custom/reservation"
 *   }
 * )
 */
class RestReservationResource extends ResourceBase {

    public $resNode;
    public $reservationTransformer;

    const MAIL_SUBJECT = 'Votre demande sur Capdel.fr';
    const MAIL_USER_FROM = 'votreprojet@capdel.com';
    const MAIL_CAPDEL_TO = 'votreprojet@capdel.com';

    const EMAIL_MODULE = 'capdel_helper';
    const EMAIL_KEY = 'custom_email';

  /**
   * Responds to POST requests.
   */
  public function post(ReservationResource $reservation) {

      $this->reservationTransformer = $reservation;

      $this->resNode = Node::create([
        'type' => 'package',
      ]);
      $this->resNode->setPublished(0);
      $this->resNode->set('moderation_state','Draft');

      $this->setAdditionalFields();
      $userResult = $this->setUserRelation();
      $entitiesResult = $this->setEntitiesRelation();

      $type = '';

      if($this->reservationTransformer->type === 'event' ) {
          $parsedQuery = [];
          parse_str($this->reservationTransformer->additional_info["url"], $parsedQuery);

          if($parsedQuery['destination'] !== null) {
              $tidDestination = $this->getNameFromTheTid($parsedQuery['destination']) ?? $parsedQuery['destination'];
              $this->resNode->set('field_pkg_sel_destination',$tidDestination);
          }

          if($parsedQuery['type_evenement'] !== null) {
              $tidType = $this->getNameFromTheTid($parsedQuery['type_evenement']) ?? $parsedQuery['type_evenement'];
              $this->resNode->set('field_pkg_sel_event_type',$tidType);
          }

          $this->reservationTransformer->additional_info['date'] = str_replace('/','-',$this->reservationTransformer->additional_info['date']);
          $this->resNode->set('field_pkg_reservation_type','Event reservation');
          $this->resNode->set('field_package_ord_date',(new \DateTime())->format("Y-m-d\Th:i:s"));
          $this->resNode->set('field_pkg_sel_date',(new \DateTime($this->reservationTransformer->additional_info["date"]))->format("Y-m-d"));
          $this->resNode->set('field_pkg_sel_pax',$this->reservationTransformer->additional_info["participants number"]);
          $this->resNode->set('field_pkg_sel_bdg_per_pax',$this->reservationTransformer->additional_info["eventPrice"]);
          $this->resNode->set('field_pkg_links_venue_url',$this->reservationTransformer->additional_info["url"]);
          $type = 'event';
      }
      else if($this->reservationTransformer->type === 'configurator' ) {
          $this->resNode->set('field_pkg_reservation_type','Config reservation');

          $data = $this->reservationTransformer->additional_info;
          $data['date'] = str_replace('/','-',$data['date']);

          //budget section
          $this->resNode->set('field_pkg_bdg_total',$data["budgetTotal"] );
          $this->resNode->set('field_pkg_bdg_package_total',$data["budgetTotalPackage"]);
          $this->resNode->set('field_pkg_bdg_remain',$data["remainingBudget"]);

          $this->resNode->set('field_package_ord_date',(new \DateTime())->format("Y-m-d\Th:i:s"));

          //selected section
          $tidDestination = $this->getNameFromTheTid($data["selected_values_formatted"]['destination']) ?? $data["selected_values_formatted"]['destination'];
          $tidType = $this->getNameFromTheTid($data["selected_values_formatted"]['type']) ?? $data["selected_values_formatted"]['type'];

          $this->resNode->set('field_pkg_sel_destination',$tidDestination);
          $this->resNode->set('field_pkg_sel_date',(new \DateTime($data['date']))->format("Y-m-d"));
          $this->resNode->set('field_pkg_sel_event_type',$tidType);
          $this->resNode->set('field_pkg_sel_bdg_per_pax',str_replace("+"," ", $data["selected_values_formatted"]['prix']));
          $this->resNode->set('field_pkg_sel_pax',$data["selected_values_formatted"]['participant']);

          //TODO: links :field_pkg_links_pkg_url
          //TODO: links :field_pkg_links_venue_url

          foreach($data['items'] as $typeArray) {
              foreach($typeArray as $item) {
                  $paragraph = Paragraph::create([
                    'type' => 'reservation_package_item',
                    'field_r_pkg_item_bundle' => $item["bundle"],
                    'field_r_pkg_item_category' => $item["category"],
                    'field_r_pkg_item_count' => $item["count"],
                    'field_r_pkg_item_dest' => $item["destination"],
                    'field_r_pkg_item_id' => $item["id"],
                    'field_r_pkg_item_price' => $item["price"] ?? '',
                    'field_r_pkg_item_title' => $item["title"],
                    'field_r_pkg_item_type' => $item["type"],
                  ]);
                  $paragraph->save();
                  $this->resNode->field_pkg_par_items->appendItem($paragraph);
              }
          }
          $type = 'configurator';
      }

      if($userResult && $entitiesResult) {
          $this->resNode->save();
          $this->integrateWithOdoo($type, 'loggedin');

          $this->sendEmailToUser($this->reservationTransformer->type);
          $this->sendEmailToCapdel();
          return new ModifiedResourceResponse('Success',200);
      }

      return new ResourceResponse('Failed',400);
  }

  protected function getNameFromTheTid($tid)
  {
      if ($tid === 'All') {
          return "All";
      }

      if ($tid > 0) {
          $termDestination = Term::load($tid);
          if ($termDestination !== null) {
              return $termDestination->getName();
          }
      }
      return false;
  }

  protected function integrateWithOdoo($type,$user) {
      $client = new OdooIntegrationPlugin();
      if($client->initialize()) {
          $transformArray = $client->transformReservation($this->resNode, $type, $user);
          $id = $client->send($transformArray);
          if($id) {
              $this->resNode->set('field_pkg_odoo_id',$id);
              $this->resNode->save();
              \Drupal::logger('odooInt')->notice('Saved entity @eid in Odoo with ID: @id',['@id' => $id, '@eid' => $this->resNode->id()]);
          }
          else {
              \Drupal::logger('odooInt')->notice('NOT Saved entity @eid in Odoo with ID: @id',['@id' => $id, '@eid' => $this->resNode->id()]);
          }
      }
  }

  public function sendEmailToUser($type) {
    $to = $this->user->getEmail();

    $firstName = '';
    // admin might not have first name
    if(!empty($this->user->field_first_name->getValue())) {
      $firstName = $this->user->field_first_name->getValue()[0]['value'];
    }

    $params['from'] = self::MAIL_USER_FROM;
    $params['subject'] = self::MAIL_SUBJECT;

    if($type == 'event') {
      $url = $this->resNode->get('field_pkg_links_venue_url')->getValue()[0]['value'];

      $node_id = $this->resNode->get('field_package_entities')->getValue()[0]['target_id'];
      $node = Node::load($node_id);
      $title = $node->getTitle();

      $params['body'] = "Bonjour $firstName,\n\n
Nous avons bien reçu votre demande pour $title : $url.\n\n
Nous vous recontacterons dans les plus brefs délais afin d'affiner ensemble votre cahier des charges.\n\n
L’ensemble des éléments affichés (prix, lieux, prestations, etc) constitue une simple proposition commerciale susceptible de modifications notamment en fonction des disponibilités. Ils ne peuvent lier contractuellement Capdel. La vente de Services proposée par Capdel ne sera considérée comme définitive qu'après confirmation de l'acceptation de la commande par le Prestataire, après prise de contact personnalisée.\n\n
A très bientôt,";
    } else {
      $params['body'] = "Bonjour $firstName,\n\n
Nous avons bien reçu votre demande et nous vous recontacterons dans les plus brefs délais afin d'affiner ensemble votre cahier des charges.\n\n
L’ensemble des éléments affichés (prix, lieux, prestations, etc) constitue une simple proposition commerciale susceptible de modifications notamment en fonction des disponibilités. Ils ne peuvent lier contractuellement Capdel. La vente de Services proposée par Capdel ne sera considérée comme définitive qu'après confirmation de l'acceptation de la commande par le Prestataire, après prise de contact personnalisée.\n\n
A très bientôt,";
    }

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;

    $mailManager = \Drupal::service('plugin.manager.mail');
    $mailManager->mail(self::EMAIL_MODULE, self::EMAIL_KEY, $to, $langcode, $params, NULL, $send);
  }

  public function sendEmailToCapdel() {
    $to = self::MAIL_CAPDEL_TO;

    $params['subject'] = $this->getCapdelEmailSubject();

    $url = $this->resNode->toUrl('edit-form', ['absolute' => true])->toString();

    $params['body'] = "Réservation: $url\n\n";
    //remove excessive new lines
    $params['body'] .= str_replace(">\n", ">", $this->resNode->field_package_additional_details->getValue()[0]['value']);

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;

    $mailManager = \Drupal::service('plugin.manager.mail');
    $mailManager->mail(self::EMAIL_MODULE, self::EMAIL_KEY, $to, $langcode, $params, NULL, $send);
  }

  private function getCapdelEmailSubject(){
    $subject = 'Evénement  ';

    if($this->reservationTransformer->type === 'event' ) {
      $subject .= 'packagé';
    } else if($this->reservationTransformer->type === 'configurator' ) {
      $subject .= 'configurateur';
    }

    $company = $this->user->field_company->getValue()[0]['value'];
    if(!empty($company)) {
      $subject .= ' ' . $company;
    }

    $firstName = $this->user->name->getValue()[0]['value'];
    if(!empty($firstName)) {
      $subject .= ' ' . $firstName;
    }

    $lastName = $this->user->field_last_name->getValue()[0]['value'];
    if(!empty($lastName)) {
      $subject .= ' ' . $lastName;
    }

    return $subject;
  }

  public function setAdditionalFields() {
      $this->resNode->set('field_package_info_json',serialize($this->reservationTransformer->additional_info));
      $this->resNode->set('field_package_additional_details',$this->html_show_array($this->reservationTransformer->additional_info));
      $this->resNode->set('title',$this->reservationTransformer->title);
  }

  public function setUserRelation() {
      if($this->reservationTransformer->user != "") {
          $user = User::load($this->reservationTransformer->user);
          $this->user = $user;
          $this->resNode->set('field_package_user',$user);
          return true;
      }
      return false;
  }

  public function setEntitiesRelation() {
      if(!empty($this->reservationTransformer->entities)) {
            $entities = Node::loadMultiple($this->reservationTransformer->entities);
            $this->resNode->set('field_package_entities',$entities);
            return true;
      }
      return false;
  }

    function do_offset($level){
        $offset = "";             // offset for subarry
        for ($i=1; $i<$level;$i++){
            $offset = $offset . "<td></td>";
        }
        return $offset;
    }

    function show_array($array, $level, $sub){

      $translateKeys = [
        'participants number' => 'Nombre de participants',
        'reservation date' => 'Date de la demande',
        'date' => 'Date',
        'url' => 'URL',
        'budgetTotal' => 'Budget total',
        'budgetTotalPackage' => 'Budget de la réservation',
        'remainingBudget' => 'Budget restant',
        'budgetTotalPerPer' => 'Budget par personne',
        'eventPrice' => 'Budget par personne',
        'items' => 'Items',
        'lieuItems' => 'Lieu',
        'menuItems' => 'Menus',
        'TBItems' => 'Team building',
        'AnimItems' => 'Animation',
        'OptionsItems' => 'Options',
        'count' => 'nombre',
        'price' => 'prix',
        'title' => 'titre',
        'category' => 'catégorie',
        'selected values' => 'Valeurs sélectionnées',
        'selected_values_formatted' => 'Format des valeurs',
      ];

      if (is_array($array) == 1){          // check if input is an array
            foreach($array as $key_val => $value) {
                $keyName = $key_val;
                if(array_key_exists($key_val, $translateKeys)){
                  $keyName = $translateKeys[$key_val];
                }
                $offset = "";
                if (is_array($value) == 1){   // array is multidimensional
                    echo "<tr>";
                    $offset = $this->do_offset($level);
                    echo $offset . "<td>" . $keyName . "</td>";
                    $this->show_array($value, $level+1, 1);
                }
                else{                        // (sub)array is not multidim
                    if ($sub != 1){          // first entry for subarray
                        echo "<tr nosub>";
                        $offset = $this->do_offset($level);
                    }
                    $sub = 0;
                    echo $offset . "<td main ".$sub.">" . $keyName .
                      "</td><td>" . urldecode($value) . "</td>";
                    echo "</tr>\n";
                }
            } //foreach $array
        }
        else{ // argument $array is not an array
            return;
        }
    }

    function html_show_array($array){
        ob_start();
        echo "<table cellspacing=\"0\" border=\"2\">\n";
        echo $this->show_array($array, 1, 0);
        echo "</table>\n";
        return ob_get_clean();
    }
}
