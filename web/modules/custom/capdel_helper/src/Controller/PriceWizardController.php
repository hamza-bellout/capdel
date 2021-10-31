<?php
namespace Drupal\capdel_helper\Controller;
use Drupal\capdel_helper\Helpers\EventHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 19.06.18
 * Time: 13:04
 */
class PriceWizardController extends ControllerBase
{
  const VARIANT_PRICES_COUNT = 16;

  public function priceWizard(NodeInterface $node) {
    $variants = $node->field_event_ref_variant->referencedEntities();
    if(!empty($variants)) {
        $variants = EventHelper::getUniqueVariantLocations($variants);
    }

    $update_action = Url::fromRoute('capdel_helper.node.price_update', ['node' => $node->id()])->toString();

    return [
      '#theme' => 'price_wizard',
      '#title' => 'Price wizard: '.$node->getTitle(),
      '#event' => $node,
      '#variants' => $variants,
      '#update_action' => $update_action,
    ];
  }

  public function priceUpdate(NodeInterface $node) {
    $prices = \Drupal::request()->request->get('price');

    foreach($prices as $variantId => $variantPrices){
      $this->updateVariantPrices($variantId, $variantPrices);
    }

    $url = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()])->toString();
    return new RedirectResponse($url);
  }

  public function updateVariantPrices($variantId, $newVariantPrices){
    $variant = Node::load($variantId);
    $variantPrices = $variant->field_event_sub_price_ppmin;
    if(count($variantPrices) < self::VARIANT_PRICES_COUNT) {
      $variantPrices->setValue(array_fill(0, self::VARIANT_PRICES_COUNT, 0));
    }

    $counter = 0;
    foreach ($newVariantPrices as $newPrice) {
      if($counter == count($variantPrices)) {
        break;
      }

      if($newPrice == '') {
        $newPrice = 0;
      }

      $variantPrices[$counter] = $newPrice;
      $counter++;
    }
    $variant->save();
  }

  /**
   * @param NodeInterface $node - event entity
   * @param Request $request
   * @return array|RedirectResponse
   */
  public function newVariantLocation(NodeInterface $node, Request $request) {
    $variant = Node::create([
      'type' => 'event_subpackage',
    ]);
    $variant->setPublished(1);
    $variant->set('moderation_state','published');
    $variant->set('field_event_sub_belongs_to', $node);
    $variant->set('title', 'Event Subpackage Event id '. $node->id().' variant no ');
    $variant->set('field_event_sub_price_ppmin', array_fill(0, self::VARIANT_PRICES_COUNT, 0));

    if ($request->getMethod() == Request::METHOD_POST) {
      $this->populateVariantLocationFromRequest($variant, $request);

      if ($this->addOrEditVariantLocation($node, $variant, $request)) {
        //update variant title after save
        $title = $variant->getTitle();
        $variant->title = $title . $variant->id();
        $variant->save();

        //update event variants relationship
        $eventVariants = $node->field_event_ref_variant->referencedEntities();
        if(!empty($eventVariants)) {
          $eventVariants[] = $variant;
          $node->set('field_event_ref_variant', $eventVariants);
        }
        else {
          $node->set('field_event_ref_variant', $variant);
        }
        $node->save();

        $url = Url::fromRoute('capdel_helper.node.price_wizard', ['node' => $node->id()])->toString();
        return new RedirectResponse($url);
      };
    }

    $update_action = Url::fromRoute('capdel_helper.node.new_event_variant_location_submit', ['node' => $node->id()])->toString();

    return [
      '#theme' => 'event_variant_location_form',
      '#title' => 'New Location: '.$node->getTitle(),
      '#event' => $node,
      '#variant' => $variant,
      '#update_action' => $update_action,
    ];
  }

  private function addOrEditVariantLocation(NodeInterface $event, NodeInterface $variant) {
    if ($this->validateVariant($event, $variant)) {
      $variant->save();
      return true;
    }
    return false;
  }

  private function populateVariantLocationFromRequest(NodeInterface $variant, Request $request){
    if ($request->get('field_event_sub_city')) {
      $variant->field_event_sub_city = $request->get('field_event_sub_city');
    }
    if ($request->get('field_event_sub_address')) {
      $variant->field_event_sub_address = $request->get('field_event_sub_address');
    }
    if ($request->get('field_event_sub_zip')) {
      $variant->field_event_sub_zip = $request->get('field_event_sub_zip');
    }
    if ($request->get('field_event_sub_geo_params')) {
      $variant->field_event_sub_geo_params = $request->get('field_event_sub_geo_params');
    }
  }

  private function validateVariant(NodeInterface $event, NodeInterface $variant) {
    $variants = $event->field_event_ref_variant->referencedEntities();

    $existingVariants = [];
    foreach ($variants as $dbVariant) {
      $fingerprint = $this->getVariantLocationFingerprint($dbVariant);

      if(!array_key_exists($fingerprint, $existingVariants)) {
        $existingVariants[$fingerprint] = $dbVariant;
      }
    }

    $variantFingerprint = $this->getVariantLocationFingerprint($variant);
    if(!array_key_exists($variantFingerprint, $existingVariants) ||
      $existingVariants[$variantFingerprint] ->id() == $variant->id()){
      return true;
    }

    drupal_set_message(t('Location with that city and address already exists.'), 'error');

    return false;
  }

  private function getVariantLocationFingerprint(NodeInterface $variant){
    $fingerprint = '';

    $city = $variant->field_event_sub_city->getValue()[0]['value'];
    if (!empty($city)) {
      $fingerprint .= $city;
    }

    $address = $variant->field_event_sub_address->getValue()[0]['value'];
    if (!empty($address)) {
      $fingerprint .= '_' . $address;
    }

    return $fingerprint;
  }

  public function editVariantLocation(NodeInterface $event, NodeInterface $variant, Request $request) {
    $city = $variant->field_event_sub_city->getValue()[0]['value'];
    $address = $variant->field_event_sub_address->getValue()[0]['value'];
    $locationInfo = $city;
    if(!empty($address)) {
      $locationInfo .= ' (' . $address . ')';
    }

    $this->populateVariantLocationFromRequest($variant, $request);

    if ($request->getMethod() == Request::METHOD_POST) {
      if ($this->addOrEditVariantLocation($event, $variant, $request)) {
        $url = Url::fromRoute('capdel_helper.node.price_wizard', ['node' => $event->id()])->toString();
        return new RedirectResponse($url);
      }
    }

    $update_action = Url::fromRoute('capdel_helper.node.edit_event_variant_location_submit', ['event' => $event->id(), 'variant' => $variant->id()])->toString();

    return [
      '#theme' => 'event_variant_location_form',
      '#title' => 'Edit Location: ' . $locationInfo,
      '#event' => $event,
      '#variant' => $variant,
      '#update_action' => $update_action,
    ];
  }

  public function deleteVariantLocation(NodeInterface $event, NodeInterface $variant, Request $request) {
    $city = $variant->field_event_sub_city->getValue()[0]['value'];
    $address = $variant->field_event_sub_address->getValue()[0]['value'];
    $locationInfo = $city;
    if(!empty($address)) {
      $locationInfo .= ' (' . $address . ')';
    }

    if ($request->getMethod() == Request::METHOD_POST) {
      $variant->delete();

      $url = Url::fromRoute('capdel_helper.node.price_wizard', ['node' => $event->id()])->toString();
      return new RedirectResponse($url);
    }

    $update_action = Url::fromRoute('capdel_helper.node.delete_event_variant_location_submit', ['event' => $event->id(), 'variant' => $variant->id()])->toString();

    return [
      '#theme' => 'event_variant_location_delete',
      '#title' => 'Are you sure you want to delete location variant: ' . $locationInfo,
      '#event' => $event,
      '#variant' => $variant,
      '#update_action' => $update_action,
    ];
  }
}
