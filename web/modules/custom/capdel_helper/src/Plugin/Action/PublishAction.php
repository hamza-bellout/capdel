<?php
/**
 * @file
 * Contains \Drupal\example\Plugin\Action\ExampleNode.
 */

namespace Drupal\capdel_helper\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Makes a node example.
 *
 * @Action(
 *   id = "capdel_helper_publish_action",
 *   label = @Translation("Make selected content example"),
 *   type = "node"
 * )
 */
class PublishAction extends ActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $moderation_information = \Drupal::service('content_moderation.moderation_information');
    if($moderation_information->isModeratedEntity($entity)) {
      $this->publishModeratedEntity($entity);
    } else {
      $this->publishBaseEntity($entity);
    }
  }

  private function publishModeratedEntity($entity) {
    if ($entity->isDefaultRevision() == TRUE) {

      // Check if $entity is in the latest revision
      $revision_ids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($entity);
      $current_revision_id = $entity->getRevisionId();
      $last_revision_id = end($revision_ids);

      if ($current_revision_id != $last_revision_id) {
        // get latest revision to publish
        $last_revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($last_revision_id);
        $this->saveModeratedEntity($last_revision);
      } else {
        $this->saveModeratedEntity($entity);
      }
    }
  }

  private function publishBaseEntity($entity) {
    $this->saveBaseEntity($entity);
  }

  private function createNewEntityRevision($entity) {
    $current_user = \Drupal::currentUser();
    $time = \Drupal::time();

    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $entity = $storage->createRevision($entity, true);

    $entity->setRevisionCreationTime($time->getRequestTime());
    $entity->setRevisionUserId($current_user->id());

    return $entity;
  }

  private function saveModeratedEntity($entity) {
    if($entity->get('moderation_state')->getString() !== 'published') {
        $newRevision = $this->createNewEntityRevision($entity);
        $newRevision->set('moderation_state', 'published');
        $newRevision->save();
    }
  }

  private function saveBaseEntity($entity) {
    if(!$entity->isPublished()) {
        $newRevision = $this->createNewEntityRevision($entity);
        $newRevision->setPublished(true);
        $newRevision->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $moderation_information = \Drupal::service('content_moderation.moderation_information');

    if ($object->getEntityType()->id() === 'node') {
      $access = $object->access('update', $account, TRUE);
      // ignore event and event_subpackage check which are overriden with workflow
      if(!$moderation_information->isModeratedEntity($object)) {
        $access->andIf($object->status->access('edit', $account, TRUE));
      }
      return $return_as_object ? $access : $access->isAllowed();
    }
    return FALSE;
  }

}
?>
