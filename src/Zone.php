<?php

/**
 * @file
 * Contains \Drupal\cloudflare\Zone.
 */

namespace Drupal\cloudflare;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\cloudflare\Exception\ComposerDependencyException;
use CloudFlarePhpSdk\ApiEndpoints\ZoneApi;
use CloudFlarePhpSdk\ApiTypes\Zone\ZoneSettings;
use CloudFlarePhpSdk\Exceptions\CloudFlareException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Zone methods for CloudFlare.
 */
class Zone implements CloudFlareZoneInterface {
  use StringTranslationTrait;

  /**
   * The settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Tracks rate limits associated with CloudFlare Api.
   *
   * @var \Drupal\cloudflare\CloudFlareStateInterface
   */
  protected $state;

  /**
   * ZoneApi object for interfacing with CloudFlare Php Sdk.
   *
   * @var \CloudFlarePhpSdk\ApiEndpoints\ZoneApi
   */
  protected $zoneApi;

  /**
   * The current cloudflare ZoneId.
   *
   * @var string
   */
  protected $zone;

  /**
   * Flag for valid credentials.
   *
   * @var bool
   */
  protected $validCredentials;

  /**
   * Checks that the composer dependencies for CloudFlare are met.
   *
   * @var \Drupal\cloudflare\CloudFlareComposerDependenciesCheckInterface
   */
  protected $cloudFlareComposerDependenciesCheck;

  /**
   * {@inheritdoc}
   */
  public static function create(ConfigFactoryInterface $config, LoggerInterface $logger, CloudFlareStateInterface $state, CloudFlareComposerDependenciesCheckInterface $check_interface) {
    $cf_config = $config->get('cloudflare.settings');
    $api_key = $cf_config->get('apikey');
    $email = $cf_config->get('email');

    // If someone has not correctly installed composer here is where we need to
    // handle it to prevent PHP error.
    try {
      $check_interface->assert();
      $zoneapi = new ZoneApi($api_key, $email);
    }
    catch (ComposerDependencyException $e) {
      $zoneapi = NULL;
    }

    return new static(
      $config,
      $logger,
      $state,
      $zoneapi,
      $check_interface
    );
  }

  /**
   * Zone constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   CloudFlare config object.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\cloudflare\CloudFlareStateInterface $state
   *   Tracks rate limits associated with CloudFlare Api.
   * @param \CloudFlarePhpSdk\ApiEndpoints\ZoneApi | NULL $zone_api
   *   ZoneApi instance for accessing api.
   * @param \Drupal\cloudflare\CloudFlareComposerDependenciesCheckInterface $check_interface
   *   Checks that composer dependencies are met.
   */
  public function __construct(ConfigFactoryInterface $config, LoggerInterface $logger, CloudFlareStateInterface $state, $zone_api, CloudFlareComposerDependenciesCheckInterface $check_interface) {
    $this->config = $config->get('cloudflare.settings');
    $this->logger = $logger;
    $this->state = $state;
    $this->zoneApi = $zone_api;
    $this->zone = $this->config->get('zone');
    $this->validCredentials = $this->config->get('valid_credentials');
    $this->cloudFlareComposerDependenciesCheck = $check_interface;
  }

  /**
   * {@inheritdoc}
   */
  public function getZoneSettings() {
    $this->cloudFlareComposerDependenciesCheck->assert();

    if (!$this->validCredentials) {
      return NULL;
    }

    try {
      $settings = $this->zoneApi->getZoneSettings($this->zone);
      $this->state->incrementApiRateCount();
      return $settings;
    }
    catch (CloudFlareException $e) {
      $this->logger->error($e->getMessage());
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateZoneSettings(ZoneSettings $zone_settings) {
    $this->cloudFlareComposerDependenciesCheck->assert();

    if (!$this->validCredentials) {
      return;
    }

    try {
      $this->zoneApi->updateZone($zone_settings);
      $this->state->incrementApiRateCount();
    }
    catch (CloudFlareException $e) {
      $this->logger->error($e->getMessage());
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listZones() {
    $this->cloudFlareComposerDependenciesCheck->assert();
    $zones = [];

    try {
      $zones = $this->zoneApi->listZones();
      $this->state->incrementApiRateCount();
    }
    catch (CloudFlareException $e) {
      $this->logger->error($e->getMessage());
      throw $e;
    }
    return $zones;
  }

  /**
   * {@inheritdoc}
   */
  public static function assertValidCredentials($apikey, $email, CloudFlareComposerDependenciesCheckInterface $composer_dependency_check, CloudFlareStateInterface $state) {
    $composer_dependency_check->assert();
    $zone_api_direct = new ZoneApi($apikey, $email);

    try {
      $zones = $zone_api_direct->listZones();
    }
    catch (Exception $e) {
      throw $e;
    }
    finally {
      $state->incrementApiRateCount();
    }

  }

}
