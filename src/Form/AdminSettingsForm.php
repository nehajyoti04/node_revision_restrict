<?php

namespace Drupal\node_revision_restrict\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AdminSettingsForm.
 *
 * @package Drupal\node_revision_restrict\Form
 */
class AdminSettingsForm extends ConfigFormBase {

  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactory $configFactory) {
    $this->configFactory = $configFactory->getEditable('node_revision_restrict.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_revision_restrict_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['node_revision_restrict.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    // Load all the bundles i.e content type of type node.
    $content_types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();

    $form['revision_restrict_group'] = array(
      '#type' => 'details',
      '#title' => t('Node revision restrict configuration'),
      '#open' => TRUE,
    );

    foreach ($content_types as $content_type_name => $content_type) {

      $restrict_content_type_default_value = $this->configFactory->get('restrict_node_revision_number_for_' . $content_type_name);
      $form['revision_restrict_group']['node_revision_restrict_content_type_' . $content_type_name] = array(
        '#type' => 'checkbox',
        '#title' => t('Content Type : <b>:content_type_title </b>', array(':content_type_title' => $content_type_name)),
        '#default_value' => isset($restrict_content_type_default_value) ? 1 : 0,
      );
      $form['revision_restrict_group']['node_revision_restrict_number_for_content_type_' . $content_type_name] = array(
        '#type' => 'textfield',
        '#size' => 10,
        '#description' => t('Enter number to restrict revisions or leave blank for no restrictions.'),
        '#title' => t('Revision limit for :content_type_title ?', array(':content_type_title' => $content_type_name)),
        '#default_value' => isset($restrict_content_type_default_value) ? $restrict_content_type_default_value : '',
        '#maxlength' => 128,
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $content_types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($content_types as $content_type_name => $content_type) {

      $node_revision_restrict_content_type = $form_state->getValue('node_revision_restrict_content_type_' . $content_type_name);
      $node_revision_restrict_number_for_content_type = $form_state->getValue('node_revision_restrict_number_for_content_type_' . $content_type_name);

      if (isset($node_revision_restrict_content_type)) {

        if ($node_revision_restrict_content_type == 1 && $node_revision_restrict_number_for_content_type == '') {
          $form['node_revision_restrict_number_for_content_type_' . $content_type_name]['#required'] = TRUE;
          $form_state->setErrorByName('node_revision_restrict_number_for_content_type_' . $content_type_name, t('Please enter numeric value you want to keep restrict number of revision for :content_type_name !', array(':content_type_name' => $content_type_name)));
        }
        elseif ($node_revision_restrict_content_type != '' && !is_numeric($node_revision_restrict_number_for_content_type)) {
          $form_state->setErrorByName('node_revision_restrict_number_for_content_type_' . $content_type_name, t('Please enter numeric value for text field <b>how many revisions do you want to keep for :content_type_name ?</b>', array(':content_type_name' => $content_type_name)));
        }
        elseif ($node_revision_restrict_content_type != '' && $node_revision_restrict_number_for_content_type < 1) {
          $form_state->setErrorByName('node_revision_restrict_number_for_content_type_' . $content_type_name, t('Please enter more than 0 for text field <b>how many revisions do you want to keep for :content_type_name ?</b>', array(':content_type_name' => $content_type_name)));
        }
      }

      if (isset($node_revision_restrict_content_type) && !empty($node_revision_restrict_number_for_content_type)) {
        if ($node_revision_restrict_content_type != 1) {
          $form['node_revision_restrict_content_type_' . $content_type_name]['#required'] = TRUE;
          $form_state->setErrorByName('node_revision_restrict_content_type_' . $content_type_name, t('Please checked :content_type_name checkbox!', array(':content_type_name' => $content_type_name)));
        }
      }
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_content_types_and_value = [];
    $not_selected_content_types = [];

    $content_types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($content_types as $content_type_name => $content_type) {
      if ($form_state->getValue('node_revision_restrict_content_type_' . $content_type_name) == 1) {
        $selected_content_types_and_value[$content_type_name] = $form_state->getValue('node_revision_restrict_number_for_content_type_' . $content_type_name);
      }
      else {
        $not_selected_content_types[$content_type_name] = $content_type_name;
      }
    }

    foreach ($selected_content_types_and_value as $content_type => $restrict_number) {
      $this->configFactory->set('restrict_node_revision_number_for_' . $content_type, $restrict_number);
    }

    if (!empty($not_selected_content_types)) {
      foreach ($not_selected_content_types as $content_type) {
        $previous_set_variable = $this->configFactory->get('restrict_node_revision_number_for_' . $content_type);
        if ($previous_set_variable) {
          \Drupal::configFactory()->getEditable('restrict_node_revision_number_for_' . $content_type)->delete();
        }
      }
    }
    $this->configFactory->save();

    drupal_set_message(t('The restrict node revision settings have been updated.'));

    parent::submitForm($form, $form_state);
  }

}
