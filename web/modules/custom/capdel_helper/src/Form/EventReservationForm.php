<?php

namespace Drupal\capdel_helper\Form;
use Drupal\capdel_helper\Helpers\NodeHelper;
use Drupal\capdel_helper\Plugin\xmlrpc\OdooIntegrationPlugin;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

class EventReservationForm extends FormBase {

    const MAIL_SUBJECT = 'Votre demande sur Capdel.fr';

    const MAIL_USER_FROM = 'votreprojet@capdel.com';
    const MAIL_CAPDEL_TO = 'votreprojet@capdel.com';
    //const MAIL_CAPDEL_TO = 'jla@tapptic.com';

    const EMAIL_MODULE = 'capdel_helper';
    const EMAIL_KEY = 'custom_email';

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'event_reservation_form';
    }
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['event_name'] = array(
          '#type' => 'textfield',
          '#title' => t('Event name'),
          '#required' => TRUE,
          '#attributes' => [
            'class' => [
              'd-none',
              'event-name'
            ]
          ],
        );
        $form['event_date'] = array(
          '#type' => 'textfield',
          '#title' => t('Event date'),
          '#required' => false,
          '#attributes' => [
            'class' => [
              'd-none',
              'event-date'
            ]
          ],
        );
        $form['event_budget'] = array(
          '#type' => 'textfield',
          '#title' => t('Event budget'),
          '#required' => false,
          '#attributes' => [
            'class' => [
              'd-none',
              'event-budget'
            ]
          ],
        );
        $form['event_pax'] = array(
          '#type' => 'textfield',
          '#title' => t('Event pax'),
          '#required' => true,
          '#attributes' => [
            'class' => [
              'd-none',
              'event-pax'
            ]
          ],
        );
        $form['event_url'] = array(
          '#type' => 'textfield',
          '#title' => t('Event url'),
          '#required' => false,
          '#maxlength' => 512,
          '#attributes' => [
            'class' => [
              'd-none',
              'event-url'
            ]
          ],
        );


        $form['societe'] = array(
          '#type' => 'textfield',
          '#title' => t('SOCIÉTÉ'),
          '#required' => TRUE,
        );
        $form['civility'] = array(
          '#type' => 'textfield',
          '#title' => t('CIVILITÉ'),
          '#required' => TRUE,
        );
        $form['nom'] = array(
          '#type' => 'textfield',
          '#title' => t('NOM'),
          '#required' => TRUE,
        );
        $form['prenom'] = array(
          '#type' => 'textfield',
          '#title' => t('PRÉNOM'),
          '#required' => TRUE,
        );
        $form['email'] = array(
          '#type' => 'textfield',
          '#title' => t('EMAIL'),
          '#required' => TRUE,
        );
        $form['phone'] = array(
          '#type' => 'textfield',
          '#title' => t('TÉLÉPHONE'),
          '#required' => TRUE,
        );

        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['#attributes']['class'][] = 'text-center';

        $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Demande de devis')];
        $form['actions']['submit']['#attributes']['class'][] = 'mt-4 anonym-reservation-submit';

        honeypot_add_form_protection($form, $form_state, array('honeypot', 'time_restriction'));

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
        // Nothing to do here.
    }


    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $date = (new \DateTime())->format("Y-m-d\Th:i:s");

        $reservation = Node::create([
          'type' => 'package',
        ]);
        $reservation->setPublished(0);
        $reservation->set('moderation_state','Draft');
        $reservation->set('field_pkg_reservation_type','Event reservation');
        $reservation->set('title', $form_state->getValue('event_name') .' - '.  $date);
        $reservation->set('field_package_ord_date', $date);
        $reservation->set('field_pkg_links_venue_url',$_SERVER['HTTP_HOST'] .$form_state->getValue('event_url'));

        $parsedQuery = [];
        parse_str($form_state->getValue('event_url'), $parsedQuery);

        if(!empty($parsedQuery['destination'])) {
            $tidDestination = $this->getNameFromTheTid($parsedQuery['destination']) ?? $parsedQuery['destination'];
            $reservation->set('field_pkg_sel_destination',$tidDestination);
        }

        if(!empty($parsedQuery['type_evenement'])) {
            $tidType = $this->getNameFromTheTid($parsedQuery['type_evenement']) ?? $parsedQuery['type_evenement'];
            $reservation->set('field_pkg_sel_event_type',$tidType);
        }

        $reservationDate = $form_state->getValue('event_date');
        if(empty($reservationDate)) {
          $reservationDate = (new \DateTime())->format("Y-m-d");
        } else {
          $reservationDate = str_replace('/','-',$reservationDate);
          $reservationDate = (new \DateTime($reservationDate))->format("Y-m-d");
        }
        $reservation->set('field_pkg_sel_date', $reservationDate);

        $reservation->set('field_pkg_sel_pax',$form_state->getValue('event_pax'));
        $reservation->set('field_pkg_sel_bdg_per_pax',$form_state->getValue('event_budget'));

        $reservation->set('field_pkg_an_company', $form_state->getValue('societe'));
        $reservation->set('field_pkg_an_civility', $form_state->getValue('civility'));
        $reservation->set('field_pkg_an_last_name', $form_state->getValue('nom'));
        $reservation->set('field_pkg_an_first_name', $form_state->getValue('prenom'));
        $reservation->set('field_pkg_an_email', $form_state->getValue('email'));
        $reservation->set('field_pkg_an_phone', $form_state->getValue('phone'));
        $reservation->set('field_pkg_is_anonym', 1);

        $allValues = $form_state->getValues();
        unset($allValues['submit'],
            $allValues['form_build_id'],
            $allValues['form_id'],
            $allValues['url'],
            $allValues['picture'],
            $allValues['honeypot_time'],
            $allValues['op']);

        $reservation->set('field_package_info_json',serialize($allValues));
        $reservation->set('field_package_additional_details',$this->html_show_array($allValues));

        $reservation->save();

        $this->integrateWithOdoo($reservation,'event', 'anonym');

        $email = $form_state->getValue('email');
        $firstName = $form_state->getValue('prenom');
        $lastName = $form_state->getValue('nom');
        $company = $form_state->getValue('societe');
        $url = $form_state->getValue('event_url');

        $this->sendEmailToUser($email, $firstName, $url);
        $this->sendEmailToCapdel($reservation, $company, $firstName, $lastName);

        $querySplit = explode('?',\Drupal::request()->getRequestUri());
        \Drupal::request()->query->remove('destination');
        $url = Url::fromUserInput($querySplit[0] , [
            'query' => ['popup' => 'reservationSuccess']
          ]
        );

        $form_state->setRedirectUrl($url);
    }

    public function sendEmailToUser($email, $firstName, $path) {
        $to = $email;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $params['from'] = self::MAIL_USER_FROM;
        $params['subject'] = self::MAIL_SUBJECT;

        $host = \Drupal::request()->getSchemeAndHttpHost();
        $url = $host.$path;

        $node = NodeHelper::getNodeFromUrl($url);
        $title = $node->getTitle();

        $params['body'] = "Bonjour $firstName,\n\n
Nous avons bien reçu votre demande pour $title : $url.\n\n
Nous vous recontacterons dans les plus brefs délais afin d'affiner ensemble votre cahier des charges.\n\n
L’ensemble des éléments affichés (prix, lieux, prestations, etc) constitue une simple proposition commerciale susceptible de modifications notamment en fonction des disponibilités. Ils ne peuvent lier contractuellement Capdel. La vente de Services proposée par Capdel ne sera considérée comme définitive qu'après confirmation de l'acceptation de la commande par le Prestataire, après prise de contact personnalisée.\n\n
A très bientôt,";

        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = true;

        $mailManager = \Drupal::service('plugin.manager.mail');
        $mailManager->mail(self::EMAIL_MODULE, self::EMAIL_KEY, $to, $langcode, $params, NULL, $send);
    }

    public function sendEmailToCapdel($reservation, $company, $first, $last) {
        $to = self::MAIL_CAPDEL_TO;

        $params['subject'] = $this->getCapdelEmailSubject($company, $first, $last);

        $url = $reservation->toUrl('edit-form', ['absolute' => true])->toString();

        $params['body'] = "Réservation: $url\n\n";
        //remove excessive new lines
        $params['body'] .= str_replace(">\n", ">", $reservation->field_package_additional_details->getValue()[0]['value']);

        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = true;

        $mailManager = \Drupal::service('plugin.manager.mail');
        $mailManager->mail(self::EMAIL_MODULE, self::EMAIL_KEY, $to, $langcode, $params, NULL, $send);
    }

    private function getCapdelEmailSubject($company, $first, $last){
        $subject = 'Evénement packagé';

        if(!empty($company)) {
            $subject .= ' ' . $company;
        }

        if(!empty($first)) {
            $subject .= ' ' . $first;
        }

        if(!empty($last)) {
            $subject .= ' ' . $last;
        }

        return $subject;
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

    function integrateWithOdoo($entity, $type, $user) {
        $client = new OdooIntegrationPlugin();
        if($client->initialize()) {
            $transformArray = $client->transformReservation($entity, $type, $user);
            $id = $client->send($transformArray);
            if($id) {
                $entity->set('field_pkg_odoo_id',$id);
                $entity->save();
                \Drupal::logger('odooInt')->notice('Saved entity @eid in Odoo with ID: @id',['@id' => $id, '@eid' => $entity->id()]);
            }
            else {
                \Drupal::logger('odooInt')->notice('NOT Saved entity @eid in Odoo with ID: @id',['@id' => $id, '@eid' => $entity->id()]);
            }
        }
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
}
