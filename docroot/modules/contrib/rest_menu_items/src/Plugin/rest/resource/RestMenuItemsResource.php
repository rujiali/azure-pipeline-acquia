<?php
/**
 * @file
 * Create the menu item REST resource.
 */

namespace Drupal\rest_menu_items\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource to get bundles by entity.
 *
 * @RestResource(
 *   id = "rest_menu_item",
 *   label = @Translation("Menu items per menu"),
 *   uri_paths = {
 *     "canonical" = "/api/menu_items/{menu_name}"
 *   }
 * )
 */
class RestMenuItemsResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * A instance of entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * A instance of the alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * A list of menu items.
   *
   * @var array
   */
  protected $menuItems = [];

  /**
   * The maximum depth we want to return the tree.
   *
   * @var int
   */
  protected $maxDepth = 0;

  /**
   * The minimum depth we want to return the tree from.
   *
   * @var int
   */
  protected $minDepth = 1;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityManagerInterface $entity_manager,
    AccountProxyInterface $current_user,
    AliasManagerInterface $alias_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->entityManager = $entity_manager;
    $this->currentUser = $current_user;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity.manager'),
      $container->get('current_user'),
      $container->get('path.alias_manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns a list of menu items for specified menu name.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing a list of bundle names.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   A HTTP Exception.
   */
  public function get($menu_name = NULL) {

    if ($menu_name) {
      // Setup variables.
      $this->setup();

      // Create the parameters.
      $parameters = new MenuTreeParameters();
      $parameters->onlyEnabledLinks();

      if (!empty($this->maxDepth)) {
        $parameters->setMaxDepth($this->maxDepth);
      }

      if (!empty($this->minDepth)) {
        $parameters->setMinDepth($this->minDepth);
      }

      // Load the tree based on this set of parameters.
      $menu_tree = \Drupal::menuTree();
      $tree = $menu_tree->load($menu_name, $parameters);

      // Return if the menu does not exist or has no entries
      if (empty($tree)) {
        return new ResourceResponse($tree);
      }

      // Transform the tree using the manipulators you want.
      $manipulators = [
        // Only show links that are accessible for the current user.
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        // Use the default sorting of menu links.
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ];

      // Module handler should be injected.
      $moduleHandler = \Drupal::moduleHandler();
      $moduleHandler->alter('rest_menu_items_manipulators', $manipulators);

      $tree = $menu_tree->transform($tree, $manipulators);

      // Finally, build a renderable array from the transformed tree.
      $menu = $menu_tree->build($tree);

      $this->getMenuItems($menu['#items'], $this->menuItems);

      // Return response
      $response = new ResourceResponse(array_values($this->menuItems));

      // Configure caching for minDepth and maxDepth parameters
      if ($response instanceof CacheableResponseInterface) {
        $response->addCacheableDependency(new RestMenuItemsCachableDepenency($menu_name, $this->minDepth, $this->maxDepth));
      }

      return $response;
    }
    throw new HttpException(t("Menu name was not provided"));
  }

  /**
   * Generate the menu tree we can use in JSON.
   *
   * @param array $tree
   *   The menu tree.
   * @param array $items
   *   The already created items.
   */
  protected function getMenuItems(array $tree, array &$items = []) {
    foreach ($tree as $item_value) {
      /* @var $org_link \Drupal\Core\Menu\MenuLinkDefault */
      $org_link = $item_value['original_link'];
      $options = $org_link->getOptions();

      // Set name to uuid or base id.
      $item_name = $org_link->getDerivativeId();
      if (empty($item_name)) {
        $item_name = $org_link->getBaseId();
      }

      /* @var $url \Drupal\Core\Url */
      $url = $item_value['url'];

      $external = $url->isExternal();
      $uuid = '';
      if ($external) {
        $uri = $url->getUri();
        $absolute = $uri;
        $relative = NULL;
      }
      else {
        try {
          $uri = $url->getInternalPath();
          $absolute = Url::fromUri('internal:/' . $uri, ['absolute' => TRUE])
            ->toString(TRUE)
            ->getGeneratedUrl();

          $relative = Url::fromUri('internal:/' . $uri, ['absolute' => FALSE])
            ->toString(TRUE)
            ->getGeneratedUrl();

          $params = Url::fromUri('internal:/' . $uri)->getRouteParameters();
          $entity_type = key($params);
          if (!empty($entity_type)) {
            $entity = \Drupal::entityTypeManager()
              ->getStorage($entity_type)
              ->load($params[$entity_type]);
            $uuid = $entity->uuid();
          }
        } catch (\UnexpectedValueException $e) {
          $absolute = $uri = $relative = '';
        }
      }

      $alias = $this->aliasManager->getAliasByPath("/$uri");

      $value = [
        'key' => $item_name,
        'title' => $org_link->getTitle(),
        'description' => $org_link->getDescription(),
        'uri' => $uri,
        'alias' => ltrim($alias, '/'),
        'external' => $external,
        'absolute' => $absolute,
        'relative' => $relative,
        'weight' => $org_link->getWeight(),
        'expanded' => $org_link->isExpanded(),
        'enabled' => $org_link->isEnabled(),
        'uuid' => $uuid,
        'options' => $options,
        'custom_properties' => $url->getOption('custom_properties'),
      ];

      if (!empty($item_value['below'])) {
        $value['below'] = [];
        $this->getMenuItems($item_value['below'], $value['below']);
      }

      $items[] = $value;
    }
  }

  /**
   * This function is used to generate some variables we need to use.
   *
   * These variables are available in the url.
   */
  private function setup() {
    // Get the current request.
    $request = \Drupal::request();

    // Get and set the max depth if available.
    $max = $request->get('max_depth');
    if (!empty($max)) {
      $this->maxDepth = $max;
    }

    // Get and set the min depth if available.
    $min = $request->get('min_depth');
    if (!empty($min)) {
      $this->minDepth = $min;
    }
  }

}
