<?php

namespace Drupal\pfe_med_connect\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom webform submission handler.
 *
 * @WebformHandler(
 *   id = "custom_webform_handler",
 *   label = @Translation("Custom handler"),
 *   category = @Translation("Custom"),
 *   description = @Translation("Custom webform submission handler."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class CustomWebformHandler extends WebformHandlerBase {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mailManager = $container->get('plugin.manager.mail');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $values = $webform_submission->getData();
    $product = $values['nom_du_medicament_ou_produit'];  
    $therapeutic_area = $values['aire_therapeutique'];  
    $department = $values['departement']; 

    if (empty($department)) {
      // If no department, do not send an email.
      return;
    }

    // Retrieve email addresses from the custom table.
    $query = \Drupal::database()->select('custom_table', 'ct');
    $query->fields('ct', ['RMR_adresse_email', 'Backup_adresse_email']);
    $query->condition('ct.Produit', $product);
    $query->condition('ct.Aire_therapeutique', $therapeutic_area);
    $query->condition('ct.Departement', $department);
    $result = $query->execute()->fetchAssoc();

    if ($result) {
      $this->sendEmail('RMR_adresse_email', $result['RMR_adresse_email'], $values);
      $this->sendEmail('Backup_adresse_email', $result['Backup_adresse_email'], $values);
    }
  }

  /**
   * Sends an email.
   *
   * @param string $key
   *   The email key.
   * @param string $emailAddress
   *   The email address.
   * @param array $values
   *   The form submission data.
   */
  private function sendEmail($key, $emailAddress, array $values) {
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params = ['values' => $values];

    $this->mailManager->mail('pfe_med_connect', $key, $emailAddress, $langcode, $params);
  }
}
