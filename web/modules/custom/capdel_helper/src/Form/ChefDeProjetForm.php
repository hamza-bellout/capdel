<?php

namespace Drupal\capdel_helper\Form;
use Drupal\capdel_helper\Plugin\QueueWorker\OdooIntegrationQueueWorker;
use Drupal\capdel_helper\Plugin\xmlrpc\OdooIntegrationPlugin;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 20.06.18
 * Time: 12:14
 */
class ChefDeProjetForm extends FormBase
{

  const MAIL_TO = 'formulaire@capdel.fr';
  const MAIL_SUBJECT = 'Nous contacter pour cette offre <company_name> <full_name>';
  const MAIL_USER_SUBJECT = 'Votre demande sur Capdel.fr';

  const MODULE = 'capdel_helper';
  const KEY = 'custom_email';

  const EVENT_TYPES = [
    'Journée d’étude',
    'Soirée d’entreprise',
    'Journée d’entreprise',
    'Séminaire résidentiel',
    'Incentive/Voyage',
    'Convention'
  ];

  public function getFormId() {
    return 'chef_de_projet_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attributes']['class'][] = 'form';

    // Display login form:
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nom').' *',
      '#required' => TRUE,
      '#attributes' => [
        'autofocus' => 'autofocus',
        'placeholder' => t('Nom'),
      ],
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prenom').' *',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => t('Prenom'),
      ],
    ];

    $form['societe'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Societe').' *',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => t('Societe'),
      ],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email').' *',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => t('Email'),
      ],
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Téléphone').' *',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => t('Téléphone'),
      ],
    ];

    $options = [];
    foreach(Self::EVENT_TYPES as $idx => $event){
      $options[$idx] = t($event);
    }
    $form['event_type'] = array(
      '#type' => 'select',
      '#title' => t('Type d\'événement'),
      '#options' => $options,
      '#attributes' => [
        'placeholder' => t('Type d\'événement'),
      ],
      "#empty_option"=> t('&nbsp;')
    );

    $form['participants'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre de personnes'),
      '#attributes' => [
        'placeholder' => t('Nombre de personnes'),
      ],
    ];

    $form['date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date'),
      '#attributes' => [
        'placeholder' => t('Date'),
        'class' => ['datepicker'],
        'pattern' => ['^(0[1-9]|[12][0-9]|3[01])[- /.](0[1-9]|1[012])[- /.](19|20)\d\d$'],
        'oninvalid' => ["setCustomValidity('Merci de renseigner une date au format JJ/MM/AAAA')"],
        'oninput' => ["setCustomValidity('')"],
      ],
      '#field_prefix' =>'<div class="datepicker-holder bg-transparent event-date">',
      '#field_suffix' =>'</div>',
    ];

    $form['place'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lieu'),
      '#attributes' => [
        'placeholder' => t('Lieu'),
      ],
    ];

    $form['budget'] = [
      '#type' => 'number',
      '#title' => $this->t('Budget Total HT'),
      '#attributes' => [
        'placeholder' => t('Budget Total HT'),
        ' min' => 0,
        'onkeypress' => 'return isNumberKey(event)'
      ],
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Commentaire').' *',
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['#attributes']['class'][] = 'text-center';

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Envoyer')];
    $form['actions']['submit']['#attributes']['class'][] = 'mt-4';

    honeypot_add_form_protection($form, $form_state, array('honeypot', 'time_restriction'));

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do here.
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $capdelEmail = $this->sendEmailToCapdel($form, $form_state);
    $userEmail = $this->sendEmailToUser($form_state);
    $this->integrateWithOdoo($form_state);

    if ($capdelEmail !== true && $userEmail !== true) {
      drupal_set_message(t('There was a problem sending your message and it was not sent.'), 'error');
    }
    else {
      $url = Url::fromUserInput(\Drupal::request()->getRequestUri(), [
          'query' => ['popup' => 'chefDeProjetSuccess']
        ]
      );
      $form_state->setRedirectUrl($url);
    }
  }
    // send to odoo instantly or push to the cron queue in case of error
    protected function integrateWithOdoo($form_state) {
        $event_type = self::EVENT_TYPES[$form_state->getValue('event_type')];
        $client = new OdooIntegrationPlugin();
        if($client->initialize()) {
            $transformArray = $client->transformForm($form_state, $event_type);
            $id = $client->send($transformArray);
            if($id) {
                \Drupal::logger('odooInt')->notice('Saved form in Odoo with ID: @id',['@id' => $id]);
            }
            else {
                // push to the cron queue
                $this->sendFormToOdooQueue($form_state);
                \Drupal::logger('odooInt')->notice('NOT Saved form in Odoo with ID: @id',['@id' => $id]);
            }
        }
    }

  private function sendFormToOdooQueue(FormStateInterface $form_state) {
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('cron_odoo_integrator');
    $item = new \stdClass();
    $item->type = OdooIntegrationQueueWorker::TYPE_FORM;
    $item->form_state = $form_state;
    $item->event_type = self::EVENT_TYPES[$form_state->getValue('event_type')];
    $queue->createItem($item);
  }

  private function sendEmailToCapdel(array &$form, FormStateInterface $form_state){
    $to = self::MAIL_TO;

    $params['subject'] = $this->getEmailSubject($form_state);
    $params['body'] = $this->getEmailBody($form, $form_state);

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;

    $mailManager = \Drupal::service('plugin.manager.mail');
    $result = $mailManager->mail(self::MODULE, self::KEY, $to, $langcode, $params, NULL, $send);

    return $result['result'];
  }

  private function sendEmailToUser(FormStateInterface $form_state){
    $to = $form_state->getValue('email');

    //there is a mixup in the form regarding first and last name
    $firstName = $form_state->getValue('last_name');

    $params['subject'] = self::MAIL_USER_SUBJECT;
    $params['body'] = "Bonjour $firstName,\n\n
      Nous avons bien reçu votre demande. Nous nous permettrons de vous recontacter dans les plus brefs délais afin d'affiner ensemble votre cahier des charges, mais nous sommes actuellement en activité partielle et de ce fait peut être un peu moins réactifs. Nous faisons au mieux !!\n\n
      Merci de votre patience\n\n
      A très bientôt,";

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;

    $mailManager = \Drupal::service('plugin.manager.mail');
    $result = $mailManager->mail(self::MODULE, self::KEY, $to, $langcode, $params, NULL, $send);

    return $result['result'];
  }

  private function getEmailSubject(FormStateInterface $form_state){
    $subject = t(self::MAIL_SUBJECT);

    if(strpos($subject, '<company_name>') !== false) {
      $subject = str_replace('<company_name>', $form_state->getValue('societe'), $subject);
    }

    if(strpos($subject, '<full_name>') !== false) {
      $subject = str_replace('<full_name>', $form_state->getValue('first_name') . ' ' . $form_state->getValue('last_name'), $subject);
    }

    return $subject;
  }

  private function getEmailBody(array $form, FormStateInterface $form_state){
    $ignore_fields = [
      'submit',
      'op',
      'url',
      'honeypot_time',
      'picture',
    ];

    $body = "<p>Nous contacter pour cette offre</p>";
    foreach($form_state->getValues() as $field => $value) {
      if(in_array($field, $ignore_fields) || strpos($field, 'form_') === 0) {
        continue;
      }
      $form_value = $value;
      if($field == 'event_type') {
        $form_value = self::EVENT_TYPES[$value];
      }
      $body .= trim($form[$field]['#title'], ' *') . ': ' . $form_value . "\n";
    }

    return $body;
  }

}
