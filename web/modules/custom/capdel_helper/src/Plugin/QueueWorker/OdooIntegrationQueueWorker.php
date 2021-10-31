<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 21.09.2018
 * Time: 17:29
 */

namespace Drupal\capdel_helper\Plugin\QueueWorker;
use Drupal\capdel_helper\Plugin\xmlrpc\OdooIntegrationPlugin;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Provides the integration for the Reservation nodes to send to odoo API.
 *
 * @QueueWorker(
 *   id = "cron_odoo_integrator",
 *   title = @Translation("Cron Odoo Integration"),
 *   cron = {"time" = 180}
 * )
 */
class OdooIntegrationQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

    const TYPE_FORM = 'form';
    const TYPE_RESERVATION = 'reservation';

    /**
     * The node storage.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $nodeStorage;
    /**
     * Creates a new NodePublishBase.
     *
     * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
     *   The node storage.
     */
    public function __construct(EntityStorageInterface $node_storage) {
        $this->nodeStorage = $node_storage;
    }
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
          $container->get('entity.manager')->getStorage('node')
        );
    }

   /**
    * {@inheritdoc}
    */
    public function processItem($data) {
      if($data->type == self::TYPE_FORM){
        $result =  $this->processFormItem($data);
      } else {
        $result =  $this->processReservationItem($data);
      }

      if(!$result) {
          throw new \Exception('Problem with sending the odoo item, returning item to the queue');
      }
      return true;
    }

    private function getNodeFieldValue($field){
      $valueField = $field->getValue();
      if($valueField &&
        isset($valueField[0]) &&
        isset($valueField[0]['value'])
      ) {
        return $valueField[0]['value'];
      }
      return "";
    }

    private function processReservationItem($data) {

        \Drupal::logger('cron:odoo:worker:process')->notice('Processing item (ID: @id) in the queue worker',['@id' => $data->id]);
        $loadedNode = Node::load($data->id);
        if($loadedNode) {

            //check if node was already exported to Odoo
            $odooId = $this->getNodeFieldValue($loadedNode->get('field_pkg_odoo_id'));
            if(!empty($odooId)){
              \Drupal::logger('cron:odoo:worker:process')->notice('Reservation (ID: @id) already exported (Odoo ID: @odooId)', [
                  '@id' => $data->id,
                  '@odooId' => $odooId,
                ]);
              return true;
            }

            //get the value
            $isAnonym = $loadedNode->get('field_pkg_is_anonym')->getString();
            //if the value is true
            if($isAnonym == "1") {
                $this->integrateWithOdoo($loadedNode,'event','anonym');
            }
            //logged in user
            else {
                $reservationType = $loadedNode->get('field_pkg_reservation_type')->getValue();
                if($reservationType &&
                  isset($reservationType[0]) &&
                  isset($reservationType[0]['value'])
                ) {
                    $reservationType = $reservationType[0]['value'];
                    if($reservationType === 'Event reservation') {
                        $this->integrateWithOdoo($loadedNode,'event','loggedin');
                    }
                    else if ($reservationType === 'Config reservation') {
                        $this->integrateWithOdoo($loadedNode,'configurator','loggedin');
                    }
                }
            }
        }
        \Drupal::logger('cron:odoo:worker:process')->notice('Finished Processing item (ID: @id) in the queue worker',['@id' => $data->id]);
        return true;
    }

    private function processFormItem($data) {
      $form_state = $data->form_state;
      $event_type = $data->event_type;
      $form_id = $form_state->getBuildInfo()['form_id'];

      $client = new OdooIntegrationPlugin();
      if($client->initialize()) {
        try {
          $transformArray = $client->transformForm($form_state, $event_type);
          $id = $client->send($transformArray);
          if ($id) {
            \Drupal::logger('odooInt')->notice('Form @formId from @email saved in Odoo with ID: @id', [
              '@formId' => $form_id,
              '@email' => $form_state->getValue('email'),
              '@id' => $id
            ]);
            return true;
          }
        } catch(\Throwable $e) {
          error_log($e);
        }

        \Drupal::logger('odooInt')->notice('NOT Form @formId from @email saved in Odoo', [
          '@formId' => $form_id,
          '@email' => $form_state->getValue('email')
        ]);
      }

      return false;
    }

    /**
     * Helper function to parse the array from [0][target_id] format (Drupal entity Reference) to normal array
     * @param $array
     *
     * @return array
     */
    public function normalizeRefArray ($array) {
        if(!$array || \count($array) == 0) {
            return [];
        }
        $returnArray = [];
        foreach($array as $row) {
            if(key_exists('target_id',$row)) {
                $returnArray[] = $row['target_id'];
            }
        }
        return $returnArray;
    }

    /**
     * Compare two array items
     * @param $arrayA
     * @param $arrayB
     *
     * @return bool
     */
    protected function arrayCompare($arrayA, $arrayB) {
        return (
          is_array($arrayA)
          && is_array($arrayB)
          && count($arrayA) == count($arrayB)
          && array_diff($arrayA, $arrayB) === array_diff($arrayB, $arrayA)
        );
    }

    protected function integrateWithOdoo($entity, $type, $user) {
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
}
