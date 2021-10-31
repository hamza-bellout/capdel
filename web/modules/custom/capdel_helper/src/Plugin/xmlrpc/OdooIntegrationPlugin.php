<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 18.10.2018
 * Time: 15:33
 */
namespace Drupal\capdel_helper\Plugin\xmlrpc;

use DateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use ripcord;
use Drupal\Core\Site\Settings;
use Ripcord_TransportException;

require_once ('ripcord.php');

class OdooIntegrationPlugin
{
    protected $common;
    protected $entity;
    protected $url;
    protected $uid;
    protected $db;
    protected $username;
    protected $password;

    protected $confPlace;

    protected const CONFIG_FORMAT = [
      '½ Journée d’étude' => 'DJE',
      "½ Journée d'étude" => 'DJE',
      'Journée d’étude' => 'JDE',
      "Journée d'étude" => 'JDE',
      'Journée d’entreprise'=> 'JDE',
      "Journée d'entreprise"=> 'JDE',
      'Soirée d’entreprise' => 'SEN',
      "Soirée d'entreprise" => 'SEN',
    ];
    protected const TYPE_EVENEMENT = [
      'All' => 'JDE',
      '½ Journée d’étude' => 'DJE',
      "½ Journée d'étude" => 'DJE',
      'Journée d’étude' => 'JDE',
      "Journée d'étude" => 'JDE',
      'Soirée d’entreprise' => 'SEN',
      "Soirée d'entreprise" => 'SEN',
      'Soirée dansante' => 'SDA',
      'Journée d’entreprise'=> 'JEN',
      "Journée d'entreprise"=> 'JEN',
      'Séminaire résidentiel' => 'SRE',
      'Mono Presta/Venue Finding' => 'MVF',
      'Incentive/Voyage' => 'IVO',
      'Incentive' => 'IVO',
      'Convention' => 'CNV',
      'Location de salles' => 'MVF',
      'Repas d’affaires' => 'APR',
      'Soirée conférence' => 'SEN'
    ];

    protected const FORMAT_VENUE_CODE = [
      'Repas d’affaires' => 'REP',
      "Repas d'affaires" => 'REP',
      '½ Journée d’étude' => 'DJE',
      "½ Journée d'étude" => 'DJE',
      'Journée d’étude' => 'JDE',
      "Journée d'étude" => 'JDE',
      'Séminaire résidentiel' => 'SEM',
      "Animation" => 'ANM',
      'Soirée conférence' => 'SRE',
      'Incentive' => 'TIM',
      'Location de salles' => 'LOC',
      'Location de salle' => 'LOC',
    ];

    protected const FORM_NAME = [
      'votre_lieu_form' => 'Votre Lieu',
      'chef_de_projet_form' => 'Chef de Projet',
      'contact_form' => 'Contact'
    ];

    protected const ODOO_ERROR_LOG = '/var/log/httpd/odoo_errors.log';

    public function initialize() {
        $this->username = settings::get('odoo_username',false);
        $this->password = settings::get('odoo_password',false);
        $this->db = settings::get('odoo_db',false);
        $this->url = settings::get('odoo_url',false);

        if($this->username &&
          $this->password &&
          $this->db &&
          $this->url
        ) {
            try {
                $this->common = ripcord::client("$this->url/xmlrpc/2/common");
                $this->uid = $this->common->authenticate($this->db, $this->username, $this->password, array());
                $this->entity = ripcord::client("$this->url/xmlrpc/2/object");
                return true;

            } catch(Ripcord_TransportException $e) {
                \Drupal::logger('odooInt')->error('Authenticate failed during init of Odoo');
                return false;
            }
        }
        if($this->uid === false) {
            \Drupal::logger('odooInt')->error('UID is null during init of Odoo');
            return false;
        }
    }

    public function send($entity) {
        $id =  $this->entity->execute_kw(
          $this->db,
          $this->uid,
          $this->password,
          'crm.lead',
          'api_create',
          [$entity]
        );

        // return the ID of the created entity in ODOO
        if(\is_array($id) && isset($id[0]) && is_numeric($id[0])) {
            return $id[0];
        }


        // if we did not get the array with created ID value from the API,
        // generate the fail response and log
        \Drupal::logger('odooInt')->error('Error while sending the entity object to odoo');
        \Drupal::logger('odooInt')->error(print_r($id,true));
        \Drupal::logger('odooInt')->notice(print_r($entity,true));

        file_put_contents ( self::ODOO_ERROR_LOG , print_r($entity) ,FILE_APPEND );
        file_put_contents ( self::ODOO_ERROR_LOG , print_r($id) , FILE_APPEND );

        return false;
    }

    private function createDefaultTransformArray(){
      return [
        'dept_code' => 'CAP',
        'origine_prospect_code' => 'PRS',
        'type_evenement_code' => 'APR',
        'zone_geo' => '',
        'location_description' => '',
        'location_id_ext' => 0,
        'location' => '',
        'config_format' => 'JDE',
        'config_format_code' => 'JDE',
        'config_total' => 0,
        'config_budget' => 0,
        'config_localisation' => '',
        'config_menu_list' => [],
        'config_activity_list' => [],
        'config_animation_list' => [],
        'config_option_list' => [],
        'customer_budget_participant' => 0,
        'config_budget_pax' => 0,
        'country_code' => 'FR',
        'etiquettes' => ['Via Google']
      ];
    }

    /**
     * @param \Drupal\node\Entity\Node $entity
     * @param $type
     * @param $user
     *
     * @return array
     */
    public function transformReservation(Node $entity, $type, $user) : array {
        // defaults array, can be overwritten
        $transformed = $this->createDefaultTransformArray();

        if($user === 'anonym') {
            $userInfo = [
              'partner_name' => $this->getValue($entity->get('field_pkg_an_company')),
              'contact_name' => $this->getValue($entity->get('field_pkg_an_first_name')) .' '. $this->getValue($entity->get('field_pkg_an_last_name')),
              'email_from' => $this->getValue($entity->get('field_pkg_an_email')),
              'phone' => $this->getValue($entity->get('field_pkg_an_phone')),
              'no_emailing' => 'True',
            ];
        }
        else {
            $clientEntityID = ($this->getValue($entity->get('field_package_user')));
            $userInfo = [];

            if($clientEntityID !== null) {
                $clientEntity = User::load($clientEntityID);
                $userInfo = [
                  'partner_name' => $this->getValue($clientEntity->get('field_company')),
                  'contact_name' => $this->getValue($clientEntity->get('field_first_name')) .' '. $this->getValue($clientEntity->get('field_last_name')),
                  'email_from' => $this->getValue($clientEntity->get('mail')),
                  'phone' => $this->getValue($clientEntity->get('field_phone')),
                  'no_emailing' => !$this->getValue($clientEntity->get('field_newsletter_subscription')),
                  'function' => $this->getValue($clientEntity->get('field_function')),
                ];
            }

        }

        if($type === 'event') {
            $countCustomerBudget = true;

            //detect first number from text
            $field_pkg_sel_pax = $this->getSingleNumberFromString($this->getValue($entity->get('field_pkg_sel_pax')));
            if(empty($field_pkg_sel_pax)){
              $field_pkg_sel_pax = 1;
              $countCustomerBudget = false;
            }

            //FIXME validate the input before operation
            $customerBudget = 0;
            if($countCustomerBudget){
              $packageBudgetPerPax = filter_var($this->getValue($entity->get('field_pkg_sel_bdg_per_pax')), FILTER_SANITIZE_NUMBER_INT);
              if(!is_numeric($field_pkg_sel_pax) || !is_numeric($packageBudgetPerPax)) {
                $customerBudget = 0;
              } else {
                $customerBudget = $field_pkg_sel_pax * $packageBudgetPerPax;
              }
            }

            $date = $this->getValue($entity->get('field_pkg_sel_date'));
            try {
              if (empty($date) || (new DateTime($date) < new DateTime('1900-01-01'))) {
                $date = date('Y-m-d');
              }
            } catch (\Exception $e) {
              $date = date('Y-m-d');
            }

            $title = $entity->getTitle();
            $pattern = '/- 20.*/';
            $title = preg_replace($pattern,'',$title);

            $reservationInfo = [
              'name' => $title,
              'customer_budget' => $customerBudget,
              'dates_souhaites' => $date,
              'offre_repere' => $title,
              'offre_id_ext' => $entity->id(),
              'offre_description' => $this->getValue($entity->get('field_pkg_links_venue_url')),
              'intitule_partner' => '',
              'config_nbre_pax' => '',
              'config_format_code' => 'JDE',
              'config_format' => 'JDE',
              'nbre_pax' => $field_pkg_sel_pax,
              'config_price_pax' => 0,
              'config_date' => $date,
              'customer_budget_participant' => $packageBudgetPerPax,
              'type_evenement_code' => 'APR',
              'zone_geo' => $this->getValue($entity->get('field_pkg_sel_destination')),
            ];

          $transformed = array_merge($transformed,$userInfo,$reservationInfo);
        }
        else if($type === 'configurator') {

            //detect first number from text
            $field_pkg_sel_pax = $this->getSingleNumberFromString($this->getValue($entity->get('field_pkg_sel_pax')));
            if(empty($field_pkg_sel_pax)){
              $field_pkg_sel_pax = 1;
            }

            //detect first number from text
            $field_pkg_sel_bdg_per_pax = $this->getSingleNumberFromString($this->getValue($entity->get('field_pkg_sel_bdg_per_pax')));
            if(empty($field_pkg_sel_bdg_per_pax)){
              $field_pkg_sel_bdg_per_pax = '';
            }

            $date = $this->getValue($entity->get('field_pkg_sel_date'));
            if(empty($date)) {
              $date = date('Y-m-d');
            }

            $configInfo = [
              'offre_repere' => $entity->getTitle(),
              'offre_id_ext' => $entity->id(),
              'offre_description' => $this->getValue($entity->get('field_pkg_links_venue_url')),
              'name' => $entity->getTitle(),
              'dates_souhaites' => $date,
              'intitule_partner' => $entity->getTitle(),

              'customer_budget' => $this->getValue($entity->get('field_pkg_bdg_total')),
              'zone_geo' => $this->getValue($entity->get('field_pkg_sel_destination')),
              'nbre_pax' => $field_pkg_sel_pax,

              'config_nbre_pax' => $field_pkg_sel_pax,
              'config_price_pax' => $this->getValue($entity->get('field_pkg_bdg_package_total')) / $this->getValue($entity->get('field_pkg_sel_pax')),
              'config_localisation' => '',

              'config_format' => self::CONFIG_FORMAT[$this->getValue($entity->get('field_pkg_sel_event_type'))] ?? 'JDE',
              'config_format_code' => self::CONFIG_FORMAT[$this->getValue($entity->get('field_pkg_sel_event_type'))] ?? 'JDE',
              'config_date' => $date,
              'config_total' => $this->getValue($entity->get('field_pkg_bdg_package_total')),
              'config_budget' => $this->getValue($entity->get('field_pkg_bdg_remain')),
              'config_budget_pax' => $field_pkg_sel_bdg_per_pax,

              'type_evenement_code' => 'CON',
            ];

            $parItems = $entity->get('field_pkg_par_items')->getValue();

            $configItemsArrays = $this->getParagraphConfigValues($parItems);

            // setting place info from the configurator Place paragraph
            $configInfo['location'] = $this->confPlace['option'];
            $configInfo['config_localisation'] = $this->confPlace['localisation'];
            $configInfo['location_id_ext'] = $this->confPlace['id_ext'];

            $alias = \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$this->confPlace['id_ext']);
            $configInfo['location_description'] = 'https://' . $_SERVER['HTTP_HOST'] . $alias;

            $transformed = array_merge($transformed,$configItemsArrays,$configInfo, $userInfo);
        }

        return $transformed;
    }

  /**
   * @param FormStateInterface $form_state
   * @param $event_type
   * @return array
   */
    public function transformForm(FormStateInterface $form_state, $event_type) {
      // defaults array, can be overwritten
      $transformed = $this->createDefaultTransformArray();

      //in forms last and first names are inverted o.O
      $form_id = $form_state->getBuildInfo()['form_id'];
      $transformed['name'] = 'Contact';
      if(array_key_exists($form_id, self::FORM_NAME)){
        $transformed['name'] = self::FORM_NAME[$form_id];
      }


      $transformed['contact_name'] = $form_state->getValue('last_name') .' '. $form_state->getValue('first_name');
      $transformed['partner_name'] = $form_state->getValue('societe');
      $transformed['email_from'] = $form_state->getValue('email');
      $transformed['phone'] = $form_state->getValue('phone');
      $transformed['type_evenement_code'] = 'JDE';
      if(array_key_exists($event_type, self::TYPE_EVENEMENT)){
        $transformed['type_evenement_code'] = self::TYPE_EVENEMENT[$event_type];
      }

      if($form_id == 'votre_lieu_form') {
          $transformed['etiquettes'] = ['Mono presta / Venuefinding'];
          $transformed['format_venue_codes'] = [self::FORMAT_VENUE_CODE[$event_type]];
          $transformed['type_evenement_code'] = 'MVF';
      }

      // CDP and Contact form venue code values left empty
      // as described according to EN_ODOO_formulaire 010219 and JLA
      if($form_id == 'chef_de_projet_form') {
          $transformed['format_venue_codes'] = [];
      }

      if($form_id == 'contact_form') {
          $transformed['format_venue_codes'] = [];
          $transformed['optin_emailing'] = $form_state->getValue('marketing');
      }

      //detect first number from text
      $participants = $this->getSingleNumberFromString($form_state->getValue('participants'));
      if($participants){
        $transformed['nbre_pax'] = $participants;
      } else {
        $transformed['nbre_pax'] = 1;
      }

      $transformed['location'] = $form_state->getValue('place');

      //detect first number from text
      $budget = $this->getSingleNumberFromString($form_state->getValue('budget'));
      if($budget) {
        $transformed['customer_budget'] = $budget;
      }

      $transformed['infos_notes'] = $form_state->getValue('description');

      // add date from the form, or use today's one
      $date = $form_state->getValue('date');
      if (!$date) {
            $transformed['config_date'] = date('Y-m-d');
      }
      else {
        $datetime = \DateTime::createFromFormat('d/m/Y', $form_state->getValue('date'));
        $transformed['dates_souhaites'] = $datetime->format('Y-m-d');
        $transformed['config_date'] = $datetime->format('Y-m-d');
      }

      return $transformed;
    }

    private function getValue($field) {
        $valueField = $field->getValue();
        if($valueField &&
            isset($valueField[0]) &&
            isset($valueField[0]['value'])
        ) {
            return $valueField[0]['value'];
        }
        else if($valueField &&
          isset($valueField[0]) &&
          isset($valueField[0]['target_id'])
        ) {
            return $valueField[0]['target_id'];
        }
        return "";
    }

    private function getSingleNumberFromString($string) {
      $matches = [];
      preg_match('/[0-9 ]+/', $string, $matches);
      if(count($matches)){
        return str_replace(' ', '', $matches[0]);
      }
    }

    private function getParagraphConfigValues($paragraphItems) {
        $menuArray = [];
        $optionArray = [];
        $animationArray = [];
        $activityArray = [];

        foreach ($paragraphItems as $paragraphArray) {
            $par = Paragraph::load($paragraphArray['target_id']);
            $item = [
              'option' => $this->getValue($par->get('field_r_pkg_item_title')),
              'id_ext' => $this->getValue($par->get('field_r_pkg_item_id')),
              'price' => $this->getValue($par->get('field_r_pkg_item_price')),
              'number' => ($this->getValue($par->get('field_r_pkg_item_count')) == 0) ? 1 : $this->getValue($par->get('field_r_pkg_item_count')) ,
            ];

            switch ($this->getValue($par->get('field_r_pkg_item_bundle'))) {
                case 'configurateur_statics' :
                    $type = $this->getValue($par->get('field_r_pkg_item_type'));
                    if($type === 'menu') {
                        $menuArray[] = $item;
                        break;
                    }
                    $optionArray[] = $item;
                    break;
                case 'configurateur_tb_and_anim' :
                    $item['localisation'] = $this->getValue($par->get('field_r_pkg_item_dest'));
                    $activityArray[] = $item;
                    break;
                case 'configurateur_animations':
                    $animationArray[] = $item;
                    break;
                case 'conf_lieu':
                    $item['localisation'] = $this->getValue($par->get('field_r_pkg_item_dest'));
                    $item['category'] = $this->getValue($par->get('field_r_pkg_item_category'));
                    $this->confPlace = $item;
                    break;
                default:
                    break;
            }
        }

        return [
          'config_option_list' => $optionArray,
          'config_animation_list' => $animationArray,
          'config_menu_list' => $menuArray,
          'config_activity_list' => $activityArray,
        ];
    }


}
