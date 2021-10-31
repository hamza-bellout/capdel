<?php

namespace Drupal\capdel_legacy_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form definition for the importer module.
 */
class ImageReorderConfigurationForm extends FormBase {

    /**
     * @var \Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;

    /**
     * ImporterConfigurationForm constructor.
     *
     * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
     */
    public function __construct(LoggerChannelInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('capdel_legacy_importer.logger.channel.importer')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'importer_configuration_image_reorder_form';
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $this->logger->info('The trigger is set for the import.', ['@message' => 'optional variable or message']);

        // call for the batchProcessor service for further processing
        $batchProcessor = \Drupal::service('capdel_legacy_importer.image_reorder_batch_processor');
    }

    /**
     * Form constructor.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['start_import'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Start reorder process'),
        );

        return $form;
    }
}
