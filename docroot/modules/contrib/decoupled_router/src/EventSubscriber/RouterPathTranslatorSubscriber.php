<?php

namespace Drupal\decoupled_router\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\decoupled_router\PathTranslatorEvent;
use Psr\Log\LoggerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Route;

/**
 * Event subscriber that processes a path translation with the router info.
 */
class RouterPathTranslatorSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The router.
   *
   * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
   */
  protected $router;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * RouterPathTranslatorSubscriber constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $router
   *   The router.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ContainerInterface $container, LoggerInterface $logger, UrlMatcherInterface $router, ModuleHandlerInterface $module_handler) {
    $this->container = $container;
    $this->logger = $logger;
    $this->router = $router;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Processes a path translation request.
   */
  public function onPathTranslation(PathTranslatorEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof CacheableJsonResponse) {
      $this->logger->error('Unable to get the response object for the decoupled router event.');
      return;
    }
    $path = $event->getPath();
    $path = $this->cleanSubdirInPath($path, $event->getRequest());
    try {
      $match_info = $this->router->match($path);
    }
    catch (ResourceNotFoundException $exception) {
      return;
    }
    catch (MethodNotAllowedException $exception) {
      $response->setStatusCode(403);
      return;
    }
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    /** @var bool $param_uses_uuid */
    list(
      $entity,
      $param_uses_uuid,
      $route_parameter_entity_key
    ) = $this->findEntityAndKeys($match_info);
    if (!$entity) {
      $this->logger->notice('A route has been found but it has no entity information.');
      return;
    }
    $response->addCacheableDependency($entity);

    $entity_type_id = $entity->getEntityTypeId();
    $canonical_url = NULL;
    try {
      $canonical_url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);
    }
    catch (EntityMalformedException $e) {
      $response->setData([
        'message' => 'Unable to build entity URL.',
        'details' => 'A valid entity was found but it was impossible to generate a valid canonical URL for it.',
      ]);
      $response->setStatusCode(500);
      watchdog_exception('decoupled_router', $e);
      return;
    }
    $entity_param = $param_uses_uuid ? $entity->id() : $entity->uuid();
    $resolved_url = Url::fromRoute($match_info[RouteObjectInterface::ROUTE_NAME], [
      $route_parameter_entity_key => $entity_param,
    ], ['absolute' => TRUE])->toString(TRUE);
    $response->addCacheableDependency($canonical_url);
    $response->addCacheableDependency($resolved_url);
    $output = [
      'resolved' => $resolved_url->getGeneratedUrl(),
      'entity' => [
        'canonical' => $canonical_url->getGeneratedUrl(),
        'type' => $entity_type_id,
        'bundle' => $entity->bundle(),
        'id' => $entity->id(),
        'uuid' => $entity->uuid(),
        'label' => $entity->label(),
      ],
    ];
    // If the route is JSON API, it means that JSON API is installed and its
    // services can be used.
    if ($this->moduleHandler->moduleExists('jsonapi')) {
      /** @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $rt_repo */
      $rt_repo = $this->container->get('jsonapi.resource_type.repository');
      $rt = $rt_repo->get($entity_type_id, $entity->bundle());
      $type_name = $rt->getTypeName();
      $jsonapi_base_path = $this->container->getParameter('jsonapi.base_path');
      $entry_point_url = Url::fromRoute('jsonapi.resource_list', [], ['absolute' => TRUE])->toString(TRUE);
      $individual = Url::fromRoute(
        sprintf('jsonapi.%s.individual', $type_name),
        [$entity_type_id => $entity->uuid()],
        ['absolute' => TRUE]
      )->toString(TRUE);
      $response->addCacheableDependency($entry_point_url);
      $response->addCacheableDependency($individual);
      $output['jsonapi'] = [
        'individual' => $individual->getGeneratedUrl(),
        'resourceName' => $type_name,
        'pathPrefix' => trim($jsonapi_base_path, '/'),
        'basePath' => $jsonapi_base_path,
        'entryPoint' => $entry_point_url->getGeneratedUrl(),
      ];
      $deprecation_message = 'This property has been deprecated and will be removed in the next version of Decoupled Router. Use @alternative instead.';
      $output['meta'] = [
        'deprecated' => [
          'jsonapi.pathPrefix' => $this->t(
            $deprecation_message, ['@alternative' => 'basePath']
          ),
        ],
      ];
    }
    $response->addCacheableDependency($entity);
    $response->setStatusCode(200);
    $response->setData($output);

    $event->stopPropagation();
  }

  /**
   * Get the underlying entity and the type of ID param enhancer for the routes.
   *
   * @param array $match_info
   *   The router match info.
   *
   * @return array
   *   The pair of \Drupal\Core\Entity\EntityInterface and bool with the
   *   underlying entity and the info weather or not it uses UUID for the param
   *   enhancement. It also returns the name of the parameter under which the
   *   entity lives in the route ('node' vs 'entity').
   */
  protected function findEntityAndKeys(array $match_info) {
    $entity = NULL;
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $match_info[RouteObjectInterface::ROUTE_OBJECT];
    $route_parameters = $route->getOption('parameters');
    $route_parameter_entity_key = 'entity';
    if (
      !empty($match_info['entity']) &&
      $match_info['entity'] instanceof EntityInterface
    ) {
      $entity = $match_info['entity'];
    }
    else {
      $entity_type_id = $this->findEntityTypeFromRoute($route);
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      // TODO: $match_info[$entity_type_id] is broken for JSON API 2.x routes.
      // Now it will be $match_info[$entity_type_id] for core and
      // $match_info['entity'] for JSON API :-(
      if (
        !empty($entity_type_id) &&
        !empty($match_info[$entity_type_id]) &&
        $match_info[$entity_type_id] instanceof EntityInterface
      ) {
        $route_parameter_entity_key = $entity_type_id;
        $entity = $match_info[$entity_type_id];
      }
    }
    $param_uses_uuid = strpos(
      $route_parameters[$route_parameter_entity_key]['converter'],
      'entity_uuid'
    ) === FALSE;

    return [$entity, $param_uses_uuid, $route_parameter_entity_key];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PathTranslatorEvent::TRANSLATE][] = ['onPathTranslation'];
    return $events;
  }

  /**
   * Extracts the entity type for the route parameters.
   *
   * If there are more than one parameter, this function will return the first
   * one.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   *
   * @return string|null
   *   The entity type ID or NULL if not found.
   */
  protected function findEntityTypeFromRoute(Route $route) {
    $parameters = $route->getOption('parameters');
    // Find the entity type for the first parameter that has one.
    return array_reduce($parameters, function ($carry, $parameter) {
      if (!$carry && !empty($parameter['type'])) {
        $parts = explode(':', $parameter['type']);
        // We know that the parameter is for an entity if the type is set to
        // 'entity:<entity-type-id>'.
        if ($parts[0] === 'entity' && !empty($parts[1])) {
          $carry = $parts[1];
        }
      }
      return $carry;
    }, NULL);
  }

  /**
   * Removes the subdir prefix from the path.
   *
   * @param $path
   *   The path that can contain the subdir prefix.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to extract the path prefix from.
   *
   * @return string
   *   The clean path.
   */
  protected function cleanSubdirInPath($path, Request $request) {
    // Remove any possible leading subdir information in case Drupal is
    // installed under http://example.com/d8/index.php
    $regexp = preg_quote($request->getBasePath(), '/');
    return preg_replace(sprintf('/^%s/', $regexp), '', $path);
  }
}
