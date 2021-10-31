<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 21.09.2018
 * Time: 17:29
 */

namespace Drupal\capdel_helper\Plugin\QueueWorker;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Provides base functionality for the TB Adder Queue Workers.
 *
 * @QueueWorker(
 *   id = "cron_tb_adder",
 *   title = @Translation("Cron TB Adder"),
 *   cron = {"time" = 60}
 * )
 */
class ConfEntityRefTBProcess extends QueueWorkerBase implements ContainerFactoryPluginInterface {
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
        \Drupal::logger('cron:menu:worker:process')->notice('Processing item (ID: @id) in the queue worker',['@id' => $data->id]);

        $query = \Drupal::entityQuery('node');
        //$query->condition('field_content_type_paragraph_name.entity.field_paragraph_field_machine_name.target_id',"NODE ID HERE");
        $query->condition('field_lieu_ref_tb',$data->id,'IN');
                $entity_ids = $query->execute();

                \Drupal::logger('cron:menu:worker:process')->notice('attached entities found: @ent',['@ent' => implode(",",$entity_ids)]);

                $loadedNode = Node::load($data->id);
                if($loadedNode) {
                    //get the value and only save it if it is different
                    $referencedPlaces = $loadedNode->get('field_tb_ref_lieu')->getValue();
                    if($referencedPlaces && \count($referencedPlaces) > 0) {
                        $referencedPlaces = $this->normalizeRefArray($referencedPlaces);
                    }

                    if(!$this->arrayCompare($referencedPlaces,$entity_ids)) {
                        $loadedNode->set('field_tb_ref_lieu',$entity_ids);
                        $loadedNode->save();
                        \Drupal::logger('cron:menu:worker:process')->notice('Item (ID: @id) saved',['@id' => $data->id]);
                    }
                    else {
                        \Drupal::logger('cron:menu:worker:process')->notice('The field value is up to date.');
                        \Drupal::logger('cron:menu:worker:process')->notice('Item (ID: @id) not saved',['@id' => $data->id]);
                    }
                }
                else {
                    \Drupal::logger('cron:menu:worker:process')->warning('Error: Item (ID: @id) not saved',['@id' => $data->id]);
                }

        return true;
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
}