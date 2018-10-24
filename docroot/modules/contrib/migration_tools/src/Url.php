<?php

namespace Drupal\migration_tools;

use Drupal\migrate\MigrateException;

/**
 * Class Url.
 *
 * In migrations it is easy to get lost in all the pathing related
 * information.  This list should help designate what is what in a migration.
 * Notice that they are all namespaced to ->pathing____ to make it easy to find
 * them and to avoid collisions with migration source data.
 *
 * $migration->pathingLegacyDirectory  [oldsite]
 * $migration->pathingLegacyHost [https://www.oldsite.com]
 * $migration->pathingRedirectCorral [redirect-oldsite]
 * $migration->pathingSectionSwap
 *   [array(‘oldsite/section’ => ‘swapped-section-a’)]
 * $migration->pathingSourceLocalBasePath [/var/www/migration-source]
 *
 * $row->fileId [/oldsite/section/blah/index.html]
 * $row->pathing->corralledUri [redirect-oldsite/section/blah/index.html]
 * $row->pathing->legacySection [section] or [section/sub-section]
 * $row->pathing->legacyUrl [https://www.oldsite.com/section/blah/index.html]
 * $row->pathing->destinationUriAlias [swapped-section-a/blah/title-based-thing]
 * $row->pathing->destinationUriRaw [node/123]
 * $row->pathing->redirectSources [Array of source CorralledUri's for creating
 * redirects in complete().
 * $this->pathing->redirectDestination [any valid url, drupal path, drupal uri.]
 */
class Url {

  /**
   * Instantiates a pathing object to reside in a $row at $row->pathing.
   *
   * @param string $file_id
   *   The file ID of the row.
   *   [/oldsite/section/blah/index.html].
   * @param string $legacy_migration_source_path
   *   The legacy directory and optional sub-directories of the source file
   *   within 'migration-source'.
   *   [oldsite] or [oldsite/section/].
   * @param string $legacy_host
   *   The host of the source content.
   *   [https://www.oldsite.com].
   * @param string $redirect_corral
   *   The base path in Drupal to uses in the redirect source source.
   *   [redirect-oldsite].
   * @param array $section_swap
   *   An array or path sections to swap if the location of the source content
   *   is going to be different from the location of the migrated content.
   *   [array(‘oldsite/section’ => ‘new-section’)].
   * @param string $source_local_base_path
   *   The environment base path to where the legacy files exist.
   *   [/var/www/migration-source].
   */
  public function __construct($file_id, $legacy_migration_source_path, $legacy_host, $redirect_corral, array $section_swap, $source_local_base_path) {
    // Establish the incoming properties.
    $this->fileId = $file_id;
    $legacy_migration_source_path = ltrim($legacy_migration_source_path, '/');
    $directories = explode('/', $legacy_migration_source_path);
    $this->legacyDirectory = array_shift($directories);
    $this->legacySection = implode('/', $directories);
    $this->legacyHost = $legacy_host;
    $this->redirectCorral = $redirect_corral;
    $this->sectionSwap = self::drupalizeSwapPaths($section_swap);
    $this->sourceLocalBasePath = $source_local_base_path;
    $this->redirectSources = [];

    // Build the items we can build at this time.
    $this->generateCorralledUri();
    $this->generateLegacyUrl();
    $this->destinationSection = (!empty($this->sectionSwap[$this->legacySection])) ? $this->sectionSwap[$this->legacySection] : $this->legacySection;

    // Create the placeholders for what might come later.
    $this->destinationUriAlias = '';
    $this->destinationUriRaw = '';
    $this->redirectDestination = '';
  }

  /**
   * Alter a path to remove leading and trailing slashes.
   *
   * @param string $path
   *   A URI path.
   *
   * @return string
   *   The drupalized path.
   */
  public static function drupalizePath($path) {
    return trim($path, '/ ');
  }

  /**
   * Alter a swapaths to remove leading and trailing slashes.
   *
   * @param array $swap_paths
   *   An array of key => value pairs where both key and values are paths.
   *
   * @return array
   *   The array with leading and trailing slashes trimmed from keys and values.
   */
  public static function drupalizeSwapPaths(array $swap_paths) {
    $new_paths = [];
    foreach ($swap_paths as $key => $value) {
      $key = self::drupalizePath($key);
      $value = self::drupalizePath($value);
      $new_paths[$key] = $value;
    }
    return $new_paths;
  }

  /**
   * Grabs legacy redirects for this node from D6 and adds $row->redirects.
   *
   * This function needs to be called in prepareRow() of your migration.
   *
   * @param object $row
   *   The object of this row.
   * @param string $db_reference_name
   *   The Drupal name/identifier of the legacy database.
   * @param object $source_connection
   *   Database source connection from migration.
   */
  public static function collectD6RedirectsToThisNode($row, $db_reference_name, $source_connection) {
    // @todo D8 Refactor
    // Gather existing redirects from legacy.
    $row->redirects = \Database::getConnection($db_reference_name, $source_connection)
      ->select('path_redirect', 'r')
      ->fields('r', ['source'])
      ->condition('redirect', "node/$row->nid")
      ->execute()
      ->fetchCol();
  }

  /**
   * Take a legacy uri, and map it to an alias.
   *
   * @param string $coralled_legacy_uri
   *   The coralled URI from the legacy site ideally coming from
   *   $row->pathing->corralledUri
   *   ex: redirect-oldsite/section/blah/index.html
   *   redirect-oldsite/section/blah/index.html?foo=bar.
   * @param string $language
   *   Language.
   *
   * @return string
   *   The Drupal alias redirected from the legacy URI.
   *   ex: swapped-section-a/blah/title-based-thing
   */
  public static function convertLegacyUriToAlias($coralled_legacy_uri, $language = LANGUAGE_NONE) {
    // @todo D8 Refactor
    // Drupal paths never begin with a / so remove it.
    $coralled_legacy_uri = ltrim($coralled_legacy_uri, '/');
    // Break out any query.
    $query = parse_url($coralled_legacy_uri, PHP_URL_QUERY);
    $query = (!empty($query)) ? self::convertUrlQueryToArray($query) : [];
    $original_uri = $coralled_legacy_uri;
    $coralled_legacy_uri = parse_url($coralled_legacy_uri, PHP_URL_PATH);

    // Most common drupal paths have no ending / so start with that.
    $legacy_uri_no_end = rtrim($coralled_legacy_uri, '/');

    $redirect = redirect_load_by_source($legacy_uri_no_end, $language, $query);
    if (empty($redirect) && ($coralled_legacy_uri != $legacy_uri_no_end)) {
      // There is no redirect found, lets try looking for one with the path /.
      $redirect = redirect_load_by_source($coralled_legacy_uri, $language, $query);
    }
    if ($redirect) {
      $nid = str_replace('node/', '', $redirect->redirect);
      // Make sure we are left with a numeric id.
      if (is_int($nid) || ctype_digit($nid)) {
        $node = node_load($nid);
        if ((!empty($node)) && (!empty($node->path)) && (!empty($node->path['alias']))) {
          return $node->path['alias'];
        }
      }

      // Check for language other than und, because the aliases are
      // intentionally saved with language undefined, even for a spanish node.
      // A spanish node, when loaded does not find an alias.
      if (!empty($node->language) && ($node->language != LANGUAGE_NONE)) {
        // Some other language in play, so lookup the alias directly.
        $path = url($redirect->redirect);
        $path = ltrim($path, '/');
        return $path;
      }

      if ($node) {
        $uri = entity_uri("node", $node);
        if (!empty($uri['path'])) {
          return $uri['path'];
        }
      }
    }

    // Made it this far with no alias found, return the original.
    return $original_uri;
  }

  /**
   * Generates a drupal-centric URI based in the redirect corral.
   *
   * @param string $pathing_legacy_directory
   *   (optional) The directory housing the migration source.
   *   ex: If var/www/migration-source/oldsite, then 'oldsite' is the directory.
   * @param string $pathing_redirect_corral
   *   (optional) The fake directory used for corralling the redirects.
   *   ex: 'redirect-oldsite'.
   *
   * @var string $this->corralledUri
   *   Created property.
   *   ex: redirect-oldsite/section/blah/index.html
   */
  public function generateCorralledUri($pathing_legacy_directory = '', $pathing_redirect_corral = '') {
    // Allow the parameters to override the property if provided.
    $pathing_legacy_directory = (!empty($pathing_legacy_directory)) ? $pathing_legacy_directory : $this->legacyDirectory;
    $pathing_redirect_corral = (!empty($pathing_redirect_corral)) ? $pathing_redirect_corral : $this->redirectCorral;
    $uri = ltrim($this->fileId, '/');
    // Swap the pathing_legacy_directory for the pathing_redirect_corral.
    $uri = str_replace($pathing_legacy_directory, $pathing_redirect_corral, $uri);
    $this->corralledUri = $uri;
  }

  /**
   * Generates a legacy website-centric URL for the source row.
   *
   * @param string $pathing_legacy_directory
   *   The directory housing the migration source.
   *   ex: If var/www/migration-source/oldsite, then 'oldsite' is the directory.
   * @param string $pathing_legacy_host
   *   The scheme and host of the original content.
   *   ex: 'https://www.oldsite.com'.
   *
   * @var string $this->legacyUrl
   *   Created property.  The location where the legacy page exists.
   *   ex: https://www.oldsite.com/section/blah/index.html
   */
  public function generateLegacyUrl($pathing_legacy_directory = '', $pathing_legacy_host = '') {
    // Allow the parameters to override the property if provided.
    $pathing_legacy_directory = (!empty($pathing_legacy_directory)) ? $pathing_legacy_directory : $this->legacyDirectory;
    $pathing_legacy_host = (!empty($pathing_legacy_host)) ? $pathing_legacy_host : $this->legacyHost;
    $uri = ltrim($this->fileId, '/');
    // Swap the pathing_legacy_directory for the $pathing_legacy_host.
    $url = str_replace($pathing_legacy_directory, $pathing_legacy_host, $uri);
    $this->legacyUrl = $url;
  }

  /**
   * Generates a drupal-centric Alias for the source row.
   *
   * @param string $pathing_section_swap
   *   An array of sections to replace
   *   ex: array('oldsite/section' => 'new-section')
   * @param string $title
   *   The title of the node or any other string that should be used as the
   *   last element in the alias.
   *   ex: '2015 A banner year for corn crop'.
   *
   * @throws MigrateException
   *   If pathauto is not available to process the title string.
   *
   * @return string
   *   A drupal ready alias based on its old location mapped to its new location
   *   and ending with the title string.
   *   ex: new-section/2015-banner-year-corn-crop
   *
   * @var string $this->legacyUrl
   *   Created property: The location where the legacy page exists.
   *   ex: new-section/2015-banner-year-corn-crop
   */
  public function generateDestinationUriAlias($pathing_section_swap, $title) {
    // @todo D8 Refactor
    // Allow the parameter to override the property if provided.
    $pathing_section_swap = (!empty($pathing_section_swap)) ? $pathing_section_swap : $this->sectionSwap;

    $directories = self::extractPath($this->fileId);
    $directories = ltrim($directories, '/');
    // Swap any sections as requested.
    $directories = str_replace(array_keys($pathing_section_swap), array_values($pathing_section_swap), $directories);

    // Remove the legacy directory if it is still present.
    $directories = explode('/', $directories);
    if ($directories[0] === $this->legacyDirectory) {
      array_shift($directories);
    }
    $directories = implode('/', $directories);

    // Attempt to process the title.
    if (module_load_include('inc', 'pathauto')) {
      $path_title = pathauto_cleanstring($title);
      $this->destinationUriAlias = "{$directories}/{$path_title}";
      $this->destinationUriAlias = pathauto_clean_alias($this->destinationUriAlias);
      // The property has been set, but return it in case assignment is desired.
      return $this->destinationUriAlias;
    }
    else {
      // Fail migration because the title can not be processed.
      Message::make('The module @module was not available to process the title.', ['@module' => 'pathauto'], Message::ERROR);
      throw new MigrateException();
    }
  }

  /**
   * Convert a relative URI from a page to an absolute URL.
   *
   * @param string $href
   *   Full URL, relative URI or partial URI. Ex:
   *   ../subsection/index.html,
   *   /section/subsection/index.html,
   *   https://www.some-external-site.com/abc/def.html.
   *   https://www.this-site.com/section/subsection/index.html.
   * @param string $base_url
   *   The location where $rel existed in html. Ex:
   *   https://www.oldsite.com/section/page.html.
   * @param string $destination_base_url
   *   Destination base URL.
   *
   * @return string
   *   The relative url transformed to absolute. Ex:
   *   https://www.oldsite.com/section/subsection/index.html,
   *   https://www.oldsite.com/section/subsection/index.html,
   *   https://www.some-external-site.com/abc/def.html.
   *   https://www.this-site.com/section/subsection/index.html.
   */
  public static function convertRelativeToAbsoluteUrl($href, $base_url, $destination_base_url) {
    if ((parse_url($href, PHP_URL_SCHEME) != '') || self::isOnlyFragment($href)) {
      // $href is already a full URL or is only a fragment (onpage anchor)
      // No processing needed.
      return $href;
    }
    else {
      // Could be a faulty URL.
      $href = self::fixSchemelessInternalUrl($href, $destination_base_url);
    }

    $parsed_base_url = parse_url($base_url);
    $parsed_href = parse_url($href);
    // Destroy base_url path if relative href is root relative.
    if ($parsed_href['path'] !== ltrim($parsed_href['path'], '/')) {
      $parsed_base_url['path'] = '';
    }

    // Make the Frankenpath.
    $path = (!empty($parsed_base_url['path'])) ? $parsed_base_url['path'] : '';
    // Cut off the file.
    $path = self::extractPath($path);

    // Join it to relative path.
    $path = "{$path}/{$parsed_href['path']}";

    // Replace '//' or '/./' or '/foo/../' with '/' recursively.
    $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
    for ($n = 1; $n > 0; $path = preg_replace($re, '/', $path, -1, $n)) {
    }

    // The $path at this point should not contain '../' or it would indicate an
    // unattainable path.
    if (stripos($path, '../') !== FALSE) {
      // We have an unattainable path like:
      // 'https://oldsite.com/../blah/index.html'
      $message = 'Unable to make absolute URL of path: "@path" on page: @page.';
      $variables = [
        '@path' => $path,
        '@page' => $base_url,
      ];
      Message::make($message, $variables, Message::ERROR, 2);
    }

    // Make sure the query and fragement exist even if they are empty.
    $parsed_href['query'] = (!empty($parsed_href['query'])) ? $parsed_href['query'] : '';
    $parsed_href['fragment'] = (!empty($parsed_href['fragment'])) ? $parsed_href['fragment'] : '';

    // Build the absolute URL.
    $absolute = [
      'scheme' => $parsed_base_url['scheme'],
      'host' => $parsed_base_url['host'],
      'path' => $path,
      'query' => $parsed_href['query'],
      'fragment' => $parsed_href['fragment'],
    ];

    // Absolute URL is ready.
    return self::reassembleURL($absolute, $destination_base_url);
  }

  /**
   * Creates a redirect from a legacy path if one does not exist.
   *
   * @param string $source_path
   *   The path or url of the legacy source. MUST be INTERNAL to this site.
   *   Ex: redirect-oldsite/section/blah/index.html,
   *   https://www.this-site.com/somepage.htm
   *   http://external-site.com/somepate.htm [only if external-site.com is in
   *   the allowed hosts array].
   * @param string $destination
   *   The destination of the redirect Ex:
   *   node/123
   *   swapped-section-a/blah/title-based-thing
   *   http://www.some-other-site.com.
   * @param string $destination_base_url
   *   Destination base URL.
   * @param array $allowed_hosts
   *   If passed, this will limit redirect creation to only urls that have a
   *   domain present in the array. Others will be rejected.
   *
   * @return bool
   *   FALSE if error.
   */
  public static function createRedirect($source_path, $destination, $destination_base_url, array $allowed_hosts = []) {
    // @todo D8 Refactor
    $alias = $destination;

    // We can not create a redirect for a URL that is not part of the domain
    // or subdomain of this site.
    if (!self::isAllowedDomain($source_path, $allowed_hosts, $destination_base_url)) {
      $message = "A redirect was NOT built for @source_path because it is not an allowed host.";
      $variables = [
        '@source_path' => $source_path,
      ];
      Message::make($message, $variables, FALSE, 2);
      return FALSE;
    }

    if (!empty($source_path)) {
      // Alter source path to remove any externals.
      $source_path = self::fixSchemelessInternalUrl($source_path, $destination_base_url);
      $source = parse_url($source_path);
      $source_path = (!empty($source['path'])) ? $source['path'] : '';
      // A path should not have a preceeding /.
      $source_path = ltrim($source['path'], '/');
      $source_options = [];
      // Check for fragments (after #hash ).
      if (!empty($source['fragment'])) {
        $source_options['fragment'] = $source['fragment'];
      }
      // Check for query parameters (after ?).
      if (!empty($source['query'])) {
        parse_str($source['query'], $query);
        $source_options['query'] = $query;
      }

      // Check to see if the source and destination or alias are the same.
      if (($source_path !== $destination) && ($source_path !== $alias)) {
        // The source and destination are different, so make the redirect.
        $redirect = redirect_load_by_source($source_path);
        if (!$redirect) {
          // The redirect does not exists so create it.
          $redirect = new \stdClass();
          redirect_object_prepare($redirect);
          $redirect->source = $source_path;
          $redirect->source_options = $source_options;
          $redirect->redirect = $destination;

          redirect_save($redirect);
          $message = 'Redirect created: @source ---> @destination';
          $variables = [
            '@source' => $source_path,
            '@destination' => $redirect->redirect,
          ];
          Message::make($message, $variables, FALSE, 1);
        }
        else {
          // The redirect already exists.
          $message = 'The redirect of @legacy already exists pointing to @alias. A new one was not created.';
          $variables = [
            '@legacy' => $source_path,
            '@alias' => $redirect->redirect,
          ];
          Message::make($message, $variables, FALSE, 1);
        }
      }
      else {
        // The source and destination are the same. So no redirect needed.
        $message = 'The redirect of @source have idential source and destination. No redirect created.';
        $variables = [
          '@source' => $source_path,
        ];
        Message::make($message, $variables, FALSE, 1);
      }
    }
    else {
      // The is no value for redirect.
      $message = 'The source path is missing. No redirect can be built.';
      $variables = [];
      Message::make($message, $variables, FALSE, 1);
    }
    return TRUE;
  }

  /**
   * Creates multiple redirects to the same destination.
   *
   * This is typically called within the migration's complete().
   *
   * @param array $redirects
   *   The paths or URIs of the legacy source. MUST be INTERNAL to this site.
   *   Ex: redirect-oldsite/section/blah/index.html,
   *   https://www.this-site.com/somepage.htm
   *   http://external-site.com/somepate.htm [only if external-site.com is in
   *   the allowed hosts array].
   * @param string $destination
   *   The destination of the redirect Ex:
   *   node/123
   *   swapped-section-a/blah/title-based-thing
   *   http://www.some-other-site.com.
   * @param string $destination_base_url
   *   Destination base URL.
   * @param array $allowed_hosts
   *   If passed, this will limit redirect creation to only urls that have a
   *   domain present in the array. Others will be rejected.
   */
  public static function createRedirectsMultiple(array $redirects, $destination, $destination_base_url, array $allowed_hosts = []) {
    foreach ($redirects as $redirect) {
      if (!empty($redirect)) {
        self::createRedirect($redirect, $destination, $destination_base_url, $allowed_hosts);
      }
    }
  }

  /**
   * Deletes any redirects associated files attached to an entity's file field.
   *
   * @param object $entity
   *   The fully loaded entity.
   * @param string $field_name
   *   The machine name of the attachment field.
   * @param string $language
   *   Optional. Defaults to LANGUAGE_NONE.
   */
  public static function rollbackAttachmentRedirect($entity, $field_name, $language = '') {
    // @todo D8 Refactor
    $field = $entity->$field_name;
    if (!empty($field[$language])) {
      foreach ($field[$language] as $delta => $item) {
        $file = file_load($item['fid']);
        $url = file_create_url($file->uri);
        $parsed_url = parse_url($url);
        $destination = ltrim($parsed_url['path'], '/');
        redirect_delete_by_path($destination);
      }
    }
  }

  /**
   * Creates redirects for files attached to a given entity's field field.
   *
   * @param object $entity
   *   The fully loaded entity.
   * @param array $source_urls
   *   A flat array of source urls that should redirect to the attachments
   *   on this entity. $source_urls[0] will redirect to the first attachment,
   *   $entity->$field_name[$language][0], and so on.
   * @param string $field_name
   *   The machine name of the attachment field.
   * @param string $language
   *   Optional. Defaults to LANGUAGE_NONE.
   */
  public static function createAttachmentRedirect($entity, array $source_urls, $field_name, $language = LANGUAGE_NONE) {
    // @todo D8 Refactor
    if (empty($source_urls)) {
      // Nothing to be done here.
      $json_entity = json_encode($entity);
      watchdog("migration_tools", "redirect was not created for attachment in entity {$json_entity}");
      return;
    }

    $field = $entity->$field_name;
    if (!empty($field[$language])) {
      foreach ($field[$language] as $delta => $item) {
        // $file = file_load($item['fid']);
        // $url = file_create_url($file->uri);
        // $parsed_url = parse_url($url);
        // $destination = ltrim($parsed_url['path'], '/');.
        $source = $source_urls[$delta];

        // Create redirect.
        $redirect = redirect_load_by_source($source);
        if (!$redirect) {
          $redirect = new \stdClass();
          redirect_object_prepare($redirect);
          $redirect->source = $source;
          $redirect->redirect = "file/{$item['fid']}/download";
          redirect_save($redirect);
        }
      }
    }
  }

  /**
   * Examines an uri and evaluates if it is an image.
   *
   * @param string $uri
   *   A uri.
   *
   * @return bool
   *   TRUE if this is an image uri, FALSE if it is not.
   */
  public static function isImageUri($uri) {
    if (preg_match('/.*\.(jpg|gif|png|jpeg)$/i', $uri) !== 0) {
      // Is an image uri.
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Fixes anchor links to PDFs so that they work in IE.
   *
   * Specifically replaces anchors like #_PAGE2 and #p2 with #page=2.
   *
   * @param object $query_path
   *   The QueryPath object with HTML markup.
   *
   * @see http://www.adobe.com/content/dam/Adobe/en/devnet/acrobat/pdfs/pdf_open_parameters.pdf
   */
  public static function fixPdfLinkAnchors($query_path) {
    $anchors = $query_path->find('a');
    foreach ($anchors as $anchor) {
      $url = $anchor->attr('href');
      $contains_pdf_anchor = preg_match('/\.pdf#(p|_PAGE)([0-9]+)/i', $url, $matches);
      if ($contains_pdf_anchor) {
        $old_anchor = $matches[1];
        $page_num = $matches[3];
        $new_anchor = 'page=' . $page_num;
        $new_url = str_replace($old_anchor, $new_anchor, $url);
        $anchor->attr('href', $new_url);
      }
    }
  }

  /**
   * Removes the host if the url is intarnal but malformed.
   *
   * A url of 'mysite.com/path1/path2' is malformed because parse_url() will
   * not recognise 'mysite.com' as the host without the scheme (http://) being
   * present.  This method will remove the host if it is for this site and make
   * the url a proper root relative path.
   *
   * @param string $url
   *   A url.
   * @param string $destination_base_url
   *   Destination base URL.
   *
   * @return string
   *   A url or path correctly modified for this site.
   */
  public static function fixSchemelessInternalUrl($url, $destination_base_url) {
    if (!empty($url)) {
      $parsed_url = parse_url($url);
      if (empty($parsed_url['scheme'])) {
        $host = parse_url($destination_base_url, PHP_URL_HOST);
        $pos = stripos($url, $host);
        if ($pos === 0) {
          // The url is starting with a site's host.  Remove it.
          $url = substr_replace($url, '', $pos, strlen($host));
        }
      }
    }
    return $url;
  }

  /**
   * Given a URL or URI return the path and nothing but the path.
   *
   * @param string $href
   *   A URL or URI looking thing.
   *   Ex:
   *   http://www.oldsite.com/section/subsection/index.html
   *   http://www.oldsite.com/section/subsection/
   *   section/subsection/.
   *
   * @return string
   *   The path not containing any filename or extenstion.
   */
  public static function extractPath($href) {
    // Leading / can confuse parse_url() so get rid of them.
    $href = ltrim($href, '/');
    $path = parse_url($href, PHP_URL_PATH);
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    if ($extension) {
      $path = pathinfo($path, PATHINFO_DIRNAME);
    }
    else {
      $path = pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_BASENAME);
    }

    return $path;
  }

  /**
   * Examines an url to see if it is within a allowed list of domains.
   *
   * @param string $url
   *   A url.
   * @param array $allowed_hosts
   *   A flat array of allowed domains. ex:array('www.site.com', 'site.com').
   * @param string $destination_base_url
   *   Destination base URL.
   *
   * @return bool
   *   TRUE if the host is within the array of allowed.
   *   TRUE if the array of allowed is empty (nothing to compare against)
   *   FALSE if the domain is not with the array of allowed.
   */
  public static function isAllowedDomain($url, array $allowed_hosts, $destination_base_url) {
    $url = self::fixSchemelessInternalUrl($url, $destination_base_url);
    $host = parse_url($url, PHP_URL_HOST);
    // Treat it as allowed until evaluated otherwise.
    $allowed = TRUE;
    if (!empty($allowed_hosts) && (is_array($allowed_hosts)) && (!empty($host))) {
      // See if the host is allowed (case insensitive).
      $allowed = in_array(strtolower($host), array_map('strtolower', $allowed_hosts));
    }
    return $allowed;
  }

  /**
   * Normalize the path to make sure paths are consistent.
   *
   * @param string $uri
   *   A URI. Ex:
   *   'somepath/path/',
   *   'somepath/path'.
   *
   * @return string
   *   The normalized URI. with path ending in / if not a file.
   *   Ex: 'somepath/path/'.
   */
  public static function normalizePathEnding($uri) {
    $uri = trim($uri);
    // If the uri is a path, not ending in a file, make sure it ends in a '/'.
    if (!empty($uri) && !pathinfo($uri, PATHINFO_EXTENSION)) {
      $uri = rtrim($uri, '/');
      $uri .= '/';
    }
    return $uri;
  }

  /**
   * Take parse_url formatted url and return the url/uri as a string.
   *
   * @param array $parsed_url
   *   An array in the format delivered by php php parse_url().
   * @param string $destination_base_url
   *   Destination base URL.
   * @param bool $return_url
   *   Toggles return of full url if TRUE, or uri if FALSE (defaults: TRUE)
   *
   * @return string
   *   URL or URI.
   *
   * @throws \Exception
   */
  public static function reassembleURL(array $parsed_url, $destination_base_url, $return_url = TRUE) {
    $url = '';

    if ($return_url) {
      // It is going to need the scheme and host if there is one.
      $default_scheme = parse_url($destination_base_url, PHP_URL_SCHEME);
      $default_host = parse_url($destination_base_url, PHP_URL_HOST);

      $scheme = (!empty($parsed_url['scheme'])) ? $parsed_url['scheme'] : $default_scheme;
      $scheme = (!empty($scheme)) ? $scheme . '://' : '';

      $host = (!empty($parsed_url['host'])) ? $parsed_url['host'] : $default_host;

      if ((empty($host)) || (empty($scheme))) {
        throw new \Exception("The base domain is needed, but has not been set. Visit /admin/config/migration_tools");
      }
      else {
        // Append / after the host to account for it being removed from path.
        $url .= "{$scheme}{$host}/";
      }
    }

    // Trim the initial '/' to be Drupal friendly in the event of no host.
    $url .= (!empty($parsed_url['path'])) ? ltrim($parsed_url['path'], '/') : '';
    $url .= (!empty($parsed_url['query'])) ? '?' . $parsed_url['query'] : '';
    $url .= (!empty($parsed_url['fragment'])) ? '#' . $parsed_url['fragment'] : '';

    return $url;
  }

  /**
   * Retrieves server or html redirect of the page if it the destination exists.
   *
   * @param object $row
   *   A row object as delivered by migrate.
   * @param object $query_path
   *   The current QueryPath object.
   * @param array $redirect_texts
   *   (Optional) array of human readable strings that preceed a link to the
   *   New location of the page ex: "this page has move to".
   *
   * @return mixed
   *   string - full URL of the validated redirect destination.
   *   string 'skip' if there is a redirect but it's broken.
   *   FALSE - no detectable redirects exist in the page.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public static function hasValidRedirect($row, $query_path, array $redirect_texts = []) {
    if (empty($row->pathing->legacyUrl)) {
      throw new MigrateException('$row->pathing->legacyUrl must be defined to look for a redirect.');
    }
    else {
      // Look for server side redirect.
      $server_side = self::hasServerSideRedirects($row->pathing->legacyUrl);
      if ($server_side) {
        // A server side redirect was found.
        return $server_side;
      }
      else {
        // Look for html redirect.
        return self::hasValidHtmlRedirect($row, $query_path, $redirect_texts);
      }
    }
  }

  /**
   * Retrieves redirects from the html of the page if it the destination exists.
   *
   * @param object $row
   *   A row object as delivered by migrate.
   * @param object $query_path
   *   The current QueryPath object.
   * @param array $redirect_texts
   *   (Optional) array of human readable strings that preceed a link to the
   *   New location of the page ex: "this page has move to".
   *
   * @return mixed
   *   string - full URL of the validated redirect destination.
   *   string 'skip' if there is a redirect but it's broken.
   *   FALSE - no detectable redirects exist in the page.
   */
  public static function hasValidHtmlRedirect($row, $query_path, array $redirect_texts = []) {
    $destination = self::getRedirectFromHtml($row, $query_path, $redirect_texts);
    if ($destination) {
      // This page is being redirected via the page.
      // Is the destination still good?
      $real_destination = self::urlExists($destination);
      if ($real_destination) {
        // The destination is good. Message and return.
        $message = "Found redirect in html -> !destination";
        $variables = ['!destination' => $real_destination];
        Message::make($message, $variables, FALSE, 2);

        return $destination;
      }
      else {
        // The destination is not functioning. Message and bail with 'skip'.
        $message = "Found broken redirect in html-> !destination";
        $variables = ['!destination' => $destination];
        Message::make($message, $variables, Message::ERROR, 2);

        return 'skip';
      }
    }
    else {
      // No redirect destination found.
      return FALSE;
    }
  }

  /**
   * Check for server side redirects.
   *
   * @param string $url
   *   The full URL to a live page.
   *   Ex: https://www.oldsite.com/section/blah/index.html,
   *   https://www.oldsite.com/section/blah/.
   *
   * @return mixed
   *   string Url of the final desitnation if there was a redirect.
   *   bool FALSE if there was no redirect.
   */
  public static function hasServerSideRedirects($url) {
    $final_url = self::urlExists($url, TRUE);
    if ($final_url && ($url === $final_url)) {
      // The initial and final urls are the same, so no redirects.
      return FALSE;
    }
    else {
      // The $final_url is different, so it must have been redirected.
      return $final_url;
    }
  }

  /**
   * Searches for files of the same name and any type .
   *
   * A search for 'xyz.htm' or just 'xyz' will return xyz.htm, xyz.pdf,
   * xyz.html, xyz.doc... if they exist in the directory.
   *
   * @param string $file_name
   *   A filename with or without the extension.
   *   Ex: 'xyz'  or 'xyz.html'.
   * @param string $directory
   *   The directory path relative to the migration source.
   *   Ex: /oldsite/section.
   * @param bool $recurse
   *   Declaring whether to scan recursively into the directory (default: FALSE)
   *   CAUTION: Setting this to TRUE creates the risk of a race condition if
   *   a file with the same name and extension is in multiple locations. The
   *   last one scanned wins.
   *
   * @return array
   *   An array keyed by file extension containing name, filename and uri.
   *   Ex: array (
   *    'pdf' => array(
   *               'name' => 'xyz',
   *               'filename'=> 'xyz.pdf',
   *               'uri'=> '/oldsite/section/xyz.pdf',
   *               'legacy_uri'=> 'migration-source/oldsite/section/xyz.pdf',
   *               'extension'=> 'pdf',
   *             ),
   *   )
   */
  public static function getAllSimilarlyNamedFiles($file_name, $directory, $recurse = FALSE) {
    $processed_files = [];
    if (!empty($file_name)) {
      $file_name = pathinfo($file_name, PATHINFO_FILENAME);
      $regex = '/^' . $file_name . '\..{3,4}$/i';

      // @todo Rework this as $this->baseDir is not available to static methods.
      $migration_source_directory = \Drupal::config('migration_tools.settings')->get('source_directory_base');

      $dir = $migration_source_directory . $directory;
      $options = [
        'key' => 'filename',
        'recurse' => $recurse,
      ];
      $files = file_scan_directory($dir, $regex, $options);
      foreach ($files as $file => $fileinfo) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $processed_files[$extension] = [
          'name' => $fileinfo->name,
          'filename' => $fileinfo->filename,
          'uri' => $fileinfo->uri,
          'legacy_uri' => str_replace($migration_source_directory . '/', '', $fileinfo->uri),
          'extension' => $extension,
        ];
      }
    }

    return $processed_files;
  }

  /**
   * Check href for  containing an fragment (ex. /blah/index.html#hello).
   *
   * @param string $href
   *   An URL or URI, relative or absolute.
   *
   * @return bool
   *   TRUE - it has a fragment.
   *   FALSE - has no fragment.
   */
  public static function hasFragment($href) {
    if (substr_count($href, "#") > 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check href for only containing a fragment (ex. #hello).
   *
   * @param string $href
   *   An URL or URI, relative or absolute.
   *
   * @return bool
   *   TRUE - it is just a fragment.
   *   FALSE - it is not just a fragment.
   */
  public static function isOnlyFragment($href) {
    $first_char = substr($href, 0, 1);
    if ($first_char === "#") {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Retrieves redirects from the html of the page (meta, javascrip, text).
   *
   * @param object $row
   *   A row object as delivered by migrate.
   * @param object $query_path
   *   The current QueryPath object.
   * @param array $redirect_texts
   *   (Optional) array of human readable strings that preceed a link to the
   *   New location of the page ex: "this page has move to".
   *
   * @return mixed
   *   string - full URL of the redirect destination.
   *   FALSE - no detectable redirects exist in the page.
   */
  public static function getRedirectFromHtml($row, $query_path, array $redirect_texts = []) {
    // Hunt for <meta> redirects via refresh and location.
    // These use only full URLs.
    $metas = $query_path->top()->find('meta');
    foreach (is_array($metas) || is_object($metas) ? $metas : [] as $meta) {
      $attributes = $meta->attr();
      $http_equiv = (!empty($attributes['http-equiv'])) ? strtolower($attributes['http-equiv']) : FALSE;
      if (($http_equiv === 'refresh') || ($http_equiv === 'location')) {
        // It has a meta refresh or meta location specified.
        // Grab the url from the content attribute.
        if (!empty($attributes['content'])) {
          $content_array = preg_split('/url=/i', $attributes['content'], -1, PREG_SPLIT_NO_EMPTY);
          // The URL is going to be the last item in the array.
          $url = trim(array_pop($content_array));
          if (filter_var($url, FILTER_VALIDATE_URL)) {
            // Seems to be a valid URL.
            return $url;
          }
        }
      }
    }

    // Hunt for Javascript redirects.
    // Checks for presence of Javascript. <script type="text/javascript">.
    $js_scripts = $query_path->top()->find('script');
    foreach (is_array($js_scripts) || is_object($js_scripts) ? $js_scripts : [] as $js_script) {
      $script_text = $js_script->text();
      $url = self::extractUrlFromJS($script_text);
      if ($url) {
        return $url;
      }
    }

    // Try to account for jQuery redirects like:
    // onLoad="setTimeout(location.href='http://www.newpage.com', '0')".
    // So many variations means we can't catch them all.  But try the basics.
    $body_html = $query_path->top()->find('body')->html();
    $search = 'onLoad=';
    $content_array = preg_split("/$search/", $body_html, -1, PREG_SPLIT_NO_EMPTY);
    // If something was found there will be > 1 element in the array.
    if (count($content_array) > 1) {
      // It had an onLoad, now check it for locations.
      $url = self::extractUrlFromJS($content_array[1]);
      if ($url) {
        return $url;
      }
    }

    // Check for human readable text redirects.
    foreach (is_array($redirect_texts) ? $redirect_texts : [] as $i => $redirect_text) {
      // Array of starts and ends to try locating.
      $wrappers = [];
      // Provide two elements: the begining and end wrappers.
      $wrappers[] = ['"', '"'];
      $wrappers[] = ["'", "'"];
      foreach ($wrappers as $wrapper) {
        $body_html = $query_path->top()->find('body')->innerHtml();
        $url = self::peelUrl($body_html, $redirect_text, $wrapper[0], $wrapper[1]);
        if ($url) {
          return $url;
        }
      }
    }
  }

  /**
   * Checks if a URL actually resolves to a 'page' on the internet.
   *
   * @param string $url
   *   A full destination URL.
   *   Ex: https://www.oldsite.com/section/blah/index.html.
   * @param bool $follow_redirects
   *   TRUE (default) if you want it to track multiple redirects to the end.
   *   FALSE if you want to only evaluate the first page request.
   *
   * @return mixed
   *   string URL - http response is valid (2xx or 3xx) and has a destination.
   *     Ex: https://www.oldsite.com/section/blah/index.html
   *   bool FALSE - https response is invalid, either 1xx, 4xx, or 5xx
   */
  public static function urlExists($url, $follow_redirects = TRUE) {
    $handle = curl_init();
    curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, $follow_redirects);
    curl_setopt($handle, CURLOPT_HEADER, 0);
    // Get the HTML or whatever is linked in $redirect_url.
    $response = curl_exec($handle);

    // Get status code.
    $http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $last_location = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);

    $url = ($follow_redirects) ? $last_location : $url;

    // Check that http code exists.
    if ($http_code) {
      // Determines first digit of http code.
      $first_digit = substr($http_code, 0, 1);
      // Filters for 2 or 3 as first digit.
      if ($first_digit == 2 || $first_digit == 3) {
        return $url;
      }
      else {
        // Invalid url.
        return FALSE;
      }
    }
  }

  /**
   * Pull a URL destination from a Javascript script.
   *
   * @param string $string
   *   String of the script contents.
   *
   * @return mixed
   *   string - the validated URL if found.
   *   bool - FALSE if no valid URL was found.
   */
  public static function extractUrlFromJS($string) {
    // Look for imposters.
    $imposters = [
      'location.protocol',
      'location.host',
    ];
    foreach ($imposters as $imposter) {
      $is_imposter = stripos($string, $imposter);
      if ($is_imposter !== FALSE) {
        // It is an imposter, so bail.
        return FALSE;
      }
    }
    // Array of items to search for.
    $searches = [
      'location.replace',
      'location.href',
      'location.assign',
      'location.replace',
      "'location'",
      'location',
      "'href'",
    ];

    // Array of starts and ends to try locating.
    $wrappers = [];
    // Provide two elements: the begining and end wrappers.
    $wrappers[] = ['"', '"'];
    $wrappers[] = ["'", "'"];

    foreach ($searches as $search) {
      foreach ($wrappers as $wrapper) {
        $url = self::peelUrl($string, $search, $wrapper[0], $wrapper[1]);
        if (!empty($url)) {
          return $url;
        }
      }
    }
    return FALSE;
  }

  /**
   * Searches $haystack for a prelude string then returns the next url found.
   *
   * @param string $haystack
   *   The html string to search through.
   * @param string $prelude_string
   *   The text that appears before the url for a redirect.
   * @param string $wrapper_start
   *   The first part that the url is wrapped in: " ' [ (.
   * @param string $wrapper_end
   *   The last part that the url is wrapped in: " ' } ).
   *
   * @return mixed
   *   string - The valid URL found.
   *   bool - FALSE if no valid URL is found.
   */
  public static function peelUrl($haystack, $prelude_string, $wrapper_start, $wrapper_end) {
    $wrapped = preg_split("/{$prelude_string}/i", $haystack, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    // If something was found there will be > 1 element in the array.
    if (count($wrapped) > 1) {
      $found = $wrapped[1];
      $start_location = stripos($found, $wrapper_start);
      // Lets set a limit to how far this will search from the $prelude_string.
      // Anything more than 75 characters ahead is risky.
      $start_location = ($start_location < 75) ? $start_location : FALSE;
      // Account for the length of the start wrapper.
      $start_location = ($start_location !== FALSE) ? $start_location + strlen($wrapper_start) : FALSE;
      // Offset the search for the end, so the start does not get found x2.
      $end_location = ($start_location !== FALSE) ? stripos($found, $wrapper_end, $start_location) : FALSE;
      // Need both a start and end to grab the middle.
      if (($start_location !== FALSE) && ($end_location !== FALSE) && ($end_location > $start_location)) {
        $url = substr($found, $start_location, $end_location - $start_location);
        $url = StringTools::superTrim($url);
        // Make sure we have a valid URL.
        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
          return $url;
        }
      }
    }
    return FALSE;
  }

  /**
   * Alter image src in page that are relative, absolute or full alter base.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteImageHrefsOnPage($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    // Find all the images on the page.
    $image_srcs = $query_path->top('img[src]');
    // Initialize summary report information.
    $image_count = $image_srcs->size();
    $report = [];
    // Loop through them all looking for src to alter.
    foreach ($image_srcs as $image) {
      $href = trim($image->attr('src'));
      $new_href = self::rewritePageHref($href, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
      // Set the new href.
      $image->attr('src', $new_href);

      if ($href !== $new_href) {
        // Something was changed so add it to report.
        $report[] = "$href changed to $new_href";
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $image_count, 'Rewrote img src');
  }

  /**
   * Alter hrefs in page if they point to non-html-page files.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteAnchorHrefsToBinaryFiles($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    $attributes = [
      'href' => 'a[href], area[href]',
      'longdesc' => 'img[longdesc]',
    ];
    $filelink_count = 0;
    $report = [];
    foreach ($attributes as $attribute => $selector) {
      // Find all the $selector on the page.
      $binary_file_links = $query_path->top($selector);
      $filelink_count += $binary_file_links->size();
      // Loop through them all looking for href to alter.
      foreach ($binary_file_links as $link) {
        $href = trim($link->attr($attribute));
        if (CheckFor::isFile($href)) {
          $new_href = self::rewritePageHref($href, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
          // Set the new href.
          $link->attr($attribute, $new_href);

          if ($href !== $new_href) {
            // Something was changed so add it to report.
            $report[] = "$attribute: $href changed to $new_href";
          }
        }
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $filelink_count, 'Rewrote binary file hrefs');
  }

  /**
   * Alter relative script source paths in page if they point to js and swf.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteScriptSourcePaths($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    $attributes = [
      'src' => 'script[src], embed[src]',
      'value' => 'param[value]',
    ];
    $script_path_count = 0;
    $report = [];
    self::rewriteFlashSourcePaths($query_path, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
    foreach ($attributes as $attribute => $selector) {
      // Find all the selector on the page.
      $links_to_pages = $query_path->top($selector);
      // Initialize summary report information.
      $script_path_count += $links_to_pages->size();
      // Loop through them all looking for src or value path to alter.
      foreach ($links_to_pages as $link) {
        $href = trim($link->attr($attribute));
        $new_href = self::rewritePageHref($href, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
        // Set the new href.
        $link->attr($attribute, $new_href);

        if ($href !== $new_href) {
          // Something was changed so add it to report.
          $report[] = "$attribute: $href changed to $new_href";
        }
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $script_path_count, 'Rewrote script src');
  }

  /**
   * Alter relative Flash source paths in page scripts.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteFlashSourcePaths($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    $scripts = $query_path->top('script[type="text/javascript"]');
    foreach ($scripts as $script) {
      $needles = [
        "'src','",
        "'movie','",
      ];
      $script_content = $script->text();
      foreach ($needles as $needle) {
        $start_loc = stripos($script_content, $needle);
        if ($start_loc !== FALSE) {
          $length_needle = strlen($needle);
          // Shift to the end of the needle.
          $start_loc = $start_loc + $length_needle;
          $end_loc = stripos($script_content, "'", $start_loc);
          $target_length = $end_loc - $start_loc;
          $old_path = substr($script_content, $start_loc, $target_length);
          if (!empty($old_path)) {
            // Process the path.
            $new_path = self::rewritePageHref($old_path, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
            // Replace.
            $script_content = str_replace("'$old_path'", "'$new_path'", $script_content);
            if ($old_path !== $new_path) {
              // The path changed, so put it back.
              $script->text($script_content);
            }
          }
        }
      }
    }
  }

  /**
   * Alter hrefs in page if they point to html-page files.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteAnchorHrefsToPages($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    $attributes = [
      'href' => 'a[href], area[href]',
      'longdesc' => 'img[longdesc]',
    ];
    $pagelink_count = 0;
    $report = [];
    foreach ($attributes as $attribute => $selector) {
      // Find all the hrefs on the page.
      $links_to_pages = $query_path->top($selector);
      // Initialize summary report information.
      $pagelink_count += $links_to_pages->size();
      // Loop through them all looking for href to alter.
      foreach ($links_to_pages as $link) {
        $href = trim($link->attr('href'));
        if (CheckFor::isPage($href)) {
          $new_href = self::rewritePageHref($href, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
          // Set the new href.
          $link->attr($attribute, $new_href);

          if ($href !== $new_href) {
            // Something was changed so add it to report.
            Message::make("$attribute: $href changed to $new_href", [], FALSE);
            $report[] = "$attribute: $href changed to $new_href";
          }
        }
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $pagelink_count, 'Rewrote page hrefs');
  }

  /**
   * Alter URIs and URLs in page that are relative, absolute or full alter base.
   *
   * Relative links will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param string $href
   *   The href from a link, img src  or img long description.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   Examples:
   *   array(
   *     'http://www.old.com/section' => 'https://www.new.com/new-section',
   *     'https://www.old.com/section' => 'https://www.new.com/new-section',
   *     'https://www.old.com/section' => '/redirect-old/new-section',
   *     'www.old.com/' => 'www.new.com',
   *     'https://www.old.com/' => 'https://www.new.com',
   *     'https:/subdomain.old.com' => 'https://www.other.com/secure',
   *     'http:/subdomain.old.com' => 'https://www.other.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   *
   * @return string
   *   The processed href.
   */
  public static function rewritePageHref($href, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    if (!empty($href)) {
      // Is this an internal path?
      $scheme = parse_url($href, PHP_URL_SCHEME);
      if (empty($scheme)) {
        // It is internal, set a flag for later use.
        $internal = TRUE;
      }

      // Fix relatives Using the $base_for_relative and file_path.
      $source_file = $base_for_relative . '/' . $file_path;
      $href = self::convertRelativeToAbsoluteUrl($href, $source_file, $destination_base_url);

      // If the href matches a $url_base_alters  swap them.
      foreach ($url_base_alters as $old_base => $new_base) {
        if (stripos($href, $old_base) !== FALSE) {
          $href = str_ireplace($old_base, $new_base, $href);
        }
      }
    }
    return $href;
  }

  /**
   * Return the url query string as an associative array.
   *
   * @param string $query
   *   The string from the query paramters of an URL.
   *
   * @return array
   *   The query paramters as an associative array.
   */
  public static function convertUrlQueryToArray($query) {
    $query_parts = explode('&', $query);
    $params = [];
    foreach ($query_parts as $param) {
      $item = explode('=', $param);
      $params[$item[0]] = $item[1];
    }

    return $params;
  }

  /**
   * Checks if given URL matches a list of candidates for a default document.
   *
   * @param string $url
   *   The URL to be tested.
   * @param string $destination_base_url
   *   Destination base URL.
   * @param array $candidates
   *   A list of potential document names that could be indexes.
   *   Defaults to "default" and "index".
   *
   * @return mixed
   *   string - The base path if a matching document is found.
   *   bool - FALSE if no matching document is found.
   */
  public static function getRedirectIfIndex($url, $destination_base_url, array $candidates = ["default", "index"]) {
    // Filter through parse_url to separate out querystrings and etc.
    $path = parse_url($url, PHP_URL_PATH);

    // Pull apart components of the file and path that we'll need to compare.
    $filename = strtolower(pathinfo($path, PATHINFO_FILENAME));
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $root_path = pathinfo($path, PATHINFO_DIRNAME);

    // Test parsed URL.
    if (!empty($filename) && !empty($extension) && in_array($filename, $candidates)) {
      // Build the new implied route (base directory plus any arguments).
      $new_url = self::reassembleURL([
        'path' => $root_path,
        'query' => parse_url($url, PHP_URL_QUERY),
        'fragment' => parse_url($url, PHP_URL_FRAGMENT),
      ], $destination_base_url, FALSE);

      return $new_url;
    }
    // Default to returning FALSE if we haven't exited already.
    return FALSE;
  }

  /**
   * Outputs the sorted contents of ->pathing to terminal for inspection.
   *
   * @param string $message
   *   (optional) A message to prepend to the output.
   */
  public function debug($message = '') {
    // Sort it by property name for ease use.
    $properties = get_object_vars($this);
    ksort($properties);
    $sorted_object = new \stdClass();
    // Rebuild a new sorted object for output.
    foreach ($properties as $property_name => $property) {
      $sorted_object->$property_name = $property;
    }

    Message::varDumpToDrush($sorted_object, "$message Contents of pathing: ");
  }

}
