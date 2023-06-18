<?php

namespace Drupal\pfe_med_connect\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "med_connect_handler",
 *   label = @Translation("med connect webform handler"),
 *   category = @Translation("Custom"),
 *   description = @Translation("Handles the form submission."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class CustomEmailHandler extends WebformHandlerBase
{

  /**
   * {@inheritdoc}
   */
  public function __construct(MailManagerInterface $mail_manager, RendererInterface $renderer)
  {
    try {
      $this->mailManager = $mail_manager;
      \Drupal::logger('pfe_med_connect')->notice('Mail manager instantiated correctly.');
    } catch (\Exception $e) {
      \Drupal::logger('pfe_med_connect')->error('Error instantiating mail manager: ' . $e->getMessage());
    }

    try {
      $this->renderer = $renderer;
      \Drupal::logger('pfe_med_connect')->notice('Renderer instantiated correctly.');
    } catch (\Exception $e) {
      \Drupal::logger('pfe_med_connect')->error('Error instantiating renderer: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('renderer')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {
    // Log that the method was called.
    \Drupal::logger('pfe_med_connect')->notice('submitForm called.');
    $values = $webform_submission->getData();
    // DEBUG: Log the form data.
    \Drupal::logger('pfe_med_connect')->notice('Form data: <pre>@data</pre>', ['@data' => print_r($values, TRUE)]);

    // Get the form values.
    $product = $values['nom_du_medicament_ou_produit'];
    $therapeutic_area = $values['aire_therapeutique'];
    $departement = $values['departement'];

    // Query the database.
    $query = \Drupal::database()->select('custom_table', 'ct');

    // Add the condition for the departement.
    $query->condition('ct.Departement', $departement);

    if (!empty($product) && empty($therapeutic_area)) {
      // Scenario 1: The user selects the product and keeps the therapeutics area field empty.
      $query->condition('ct.Produit', $product);
    } elseif (empty($product) && !empty($therapeutic_area)) {
      // Scenario 2: The user selects the therapeutics area and keeps the Product field empty.
      $query->condition('ct.Aire_therapeutique', $therapeutic_area);
      $query->isNull('ct.Produit');
    } else {
      // Scenario 3: The user selects the product and therapeutics area.
      $query->condition('ct.Produit', $product);
      $query->condition('ct.Aire_therapeutique', $therapeutic_area);
    }

    $query->fields('ct', ['RMR_adresse_email', 'Backup_adresse_email']);

    $results = $query->execute()->fetchAll();

    if (!empty($results)) {
      // Prepare the mail parameters.
      $params = [
        'values' => $values,
      ];

      foreach ($results as $result) {
        // Send a mail to the MSL email.
        $this->mailManager->mail('pfe_med_connect', 'notification', $result->RMR_adresse_email, 'en', $params);

        // Send a mail to the Backup email.
        $this->mailManager->mail('pfe_med_connect', 'notification', $result->Backup_adresse_email, 'en', $params);
      }
    }
  }
}
