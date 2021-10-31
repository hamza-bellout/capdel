<?php
namespace Drupal\capdel_helper\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 19.06.18
 * Time: 13:04
 */
class ImageController extends ControllerBase
{
  public function cloneImage(MediaInterface $media) {
    $newFile = null;

    $files = $media->field_media_image->referencedEntities();
    if(count($files)){
      $file = $media->field_media_image->referencedEntities()[0];
      $uri = $file->getFileUri();
      $newUri = file_unmanaged_copy($uri, $uri);
      if(!$newUri){
        $url = Url::fromUserInput('/admin/content/media', [])->toString();
        return new RedirectResponse($url);
      }
      $pathinfo = pathinfo($newUri);

      $newFile = File::create([
        'filename' => $pathinfo['basename'],
        'uri' => $newUri,
        'uid' => 1,
        'status' => 1,
      ]);
      $newFile->setPermanent();
      $newFile->save();
    }

    $newMedia = Media::create([
      'bundle' => 'image',
    ]);

    $newMedia->set('name', 'CLONE ' . $media->name->value);
    $newMedia->set('field_media_image_title', $media->field_media_image_title->value);
    $newMedia->set('field_media_image_', $media->field_media_image_->value);
    $newMedia->set('field_media_image_is_valid', 1);
    $newMedia->set('field_media_image_legacy_name', $media->field_media_image_legacy_name->value);

    if($newFile) {
      $newMedia->set('field_media_image', [
        'target_id' => $newFile->id(),
        'alt' => $media->field_media_image[0]->alt,
        'title' => $media->field_media_image[0]->title
      ]);
    }

    $newMedia->save();

    $params = ['media' => intval($newMedia->id())];
    $destination = \Drupal::request()->query->get('destination');
    if($destination){
      \Drupal::request()->query->remove('destination');
      $params['destination'] = $destination;
    }
    $url = Url::fromRoute('entity.media.edit_form', $params)->toString();

    return new RedirectResponse($url);
  }
}
