<?php

namespace Drupal\migration_tools\Modifier;

use Drupal\migration_tools\QpHtml;
use Drupal\migration_tools\StringTools;
use Drupal\migration_tools\Url;

/**
 * The DomModifier defining removers, and changers.
 *
 * Removers must:
 *   Be protected functions that remove elements from the QueryPath object.
 *   Return a count of the items removed.
 *   Follow the naming patten removeThingToBeRemoved().
 *
 * Changers must:
 *   Be protected functions that alter elements from the QueryPath object.
 *   Return a count of the items changed.
 *   Follow the naming patten changeDescriptionOfChange().
 */
class DomModifier extends Modifier {
  protected $queryPath;

  /**
   * Constructor.
   *
   * @param object $query_path
   *   The query path object by reference.
   */
  public function __construct(&$query_path = NULL) {
    $this->queryPath = $query_path;
  }

  /**
   * Set Query Path.
   *
   * @param object $query_path
   *   Query Path.
   */
  public function setQueryPath(&$query_path) {
    $this->queryPath = $query_path;
  }

  /**
   * {@inheritdoc}
   */
  public function runModifier($method_name, array $arguments = []) {
    // Reset QueryPath pointer to top of document.
    $this->queryPath->top();
    return parent::runModifier($method_name, $arguments);
  }

  /**
   * Change any HTML class from one to a new classname.
   *
   * @param string $original_classname
   *   The classname to change from.
   * @param string $new_classname
   *   The new classname.  Removes the class if empty.
   *
   * @return int
   *   Count of items removed.
   */
  protected function changeClassName($original_classname, $new_classname = '') {
    $count = 0;
    if (!empty($original_classname)) {
      $elements = $this->queryPath->find(".{$original_classname}");
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        if (empty($new_classname)) {
          $element->removeAttr('class');
        }
        else {
          $element->attr('class', $new_classname);
        }

        $count++;
      }
    }

    return $count;
  }

  /**
   * Remove a class from a class from all elements.
   *
   * @param string $classname
   *   The classname to remove.
   *
   * @return int
   *   Count of items removed.
   */
  protected function changeRemoveClassName($classname) {
    return $this->changeClassName($classname);
  }

  /**
   * Remove all tables that are empty or contain only whitespace.
   *
   * @return int
   *   Count of items removed.
   */
  protected function removeEmptyTables() {
    $count = 0;
    $tables = $this->queryPath->find('table');
    foreach ((is_object($tables)) ? $tables : [] as $table) {
      $table_contents = $table->text();
      // Remove whitespace in order to evaluate if it is empty.
      $table_contents = StringTools::superTrim($table_contents);

      if (empty($table_contents)) {
        $table->remove();
        $count++;
      }
    }

    return $count;
  }

  /**
   * Remover for all matching selector on the page.
   *
   * @param string $selector
   *   The selector to find.
   *
   * @return int
   *   Count of items removed.
   */
  protected function removeSelectorAll($selector) {
    $count = 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        $element->remove();
        $count++;
      }
    }

    return $count;
  }

  /**
   * Remover for Nth  selector on the page.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $n
   *   (optional) The depth to find.  Default: first item n=1.
   *
   * @return int
   *   Count of items removed.
   */
  protected function removeSelectorN($selector, $n = 1) {
    $n = ($n > 0) ? $n - 1 : 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $i => $element) {
        if ($i == $n) {
          $element->remove();

          return 1;
        }
      }
    }

    return 0;
  }

  /**
   * Remove style attribute from selector.
   *
   * @param object $selector
   *   The selector to find.
   *
   * @return int
   *   Count of style attributes removed.
   */
  protected function removeStyleAttr($selector) {
    $count = 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        $element->removeAttr('style');
        $count++;
      }
    }

    return $count;
  }

  /**
   * Like match, but removes all matching elements.
   *
   * @param string $selector
   *   The CSS selector for the element to be matched.
   * @param string $needle
   *   The text string for which to search.
   * @param string $function
   *   The function used to get the haystack. E.g., 'attr' if searching for
   *   a specific attribute value, 'html', 'txt'.
   * @param string $parameter
   *   A parameter to be passed into the defined $function.
   */
  public function matchRemoveAll($selector, $needle, $function, $parameter = NULL) {
    $matches = QpHtml::matchAll($this->queryPath, $selector, $needle, $function, $parameter);
    foreach ($matches as $match) {
      $match->remove();
    }
  }

  /**
   * Examine all img longdesc attr in qp and remove any that point to images.
   */
  protected function removeFaultyImgLongdesc() {
    QpHtml::removeFaultyImgLongdesc($this->queryPath);
  }

  /**
   * Empty anchors without name attribute will be stripped by ckEditor.
   */
  protected function fixNamedAnchors() {
    QpHtml::fixNamedAnchors($this->queryPath);
  }

  /**
   * Removes all html comments from querypath document.
   */
  protected function removeComments() {
    QpHtml::removeComments($this->queryPath);
  }

  /**
   * Removes elements matching CSS selectors.
   *
   * @param array $selectors
   *   An array of selectors to remove.
   */
  protected function removeElements(array $selectors) {
    QpHtml::removeElements($this->queryPath, $selectors);
  }

  /**
   * Removes a wrapping element, leaving child elements intact.
   *
   * @param array $selectors
   *   An array of selectors for the wrapping element(s).
   */
  protected function removeWrapperElements(array $selectors) {
    QpHtml::removeWrapperElements($this->queryPath, $selectors);
  }

  /**
   * Removes elements matching CSS selectors.
   *
   * @param array $selectors
   *   An array of selectors for the wrapping element(s).
   */
  protected function rewrapElements(array $selectors) {
    foreach ($selectors as $element => $new_wrapper) {
      // Make sure the array key is not just an array index.
      if (is_string($element) && !is_numeric($element)) {
        QpHtml::rewrapElements($this->queryPath, [$element], $new_wrapper);
      }
    }
  }

  /**
   * Convert all relative links to absolute if base href if set.
   */
  public function convertBaseHrefLinks() {
    // Get base href URL.
    $base = $this->queryPath->top('head')->find('base');
    if ($base) {
      $base_href = $base->attr('href');

      // Attributes to check for relative URLs.
      $attributes = [
        'href' => 'a[href], area[href]',
        'longdesc' => 'img[longdesc]',
        'src' => 'img[src], script[src], embed[src]',
        'value' => 'param[value]',

      ];
      foreach ($attributes as $attribute => $selector) {

        $file_links = $this->queryPath->top($selector);
        foreach ($file_links as $file_link) {
          $href = trim($file_link->attr($attribute));
          $href_pieces = parse_url($href);
          if (count($href_pieces) && empty($href_pieces['scheme'])) {
            // No scheme set, must be a relative internal URL.

            $new_href = $base_href;
            $new_href .= (!empty($href_pieces['path'])) ? ltrim($href_pieces['path'], '/') : '';
            $new_href .= (!empty($href_pieces['query'])) ? '?' . $href_pieces['query'] : '';
            $new_href .= (!empty($href_pieces['fragment'])) ? '#' . $href_pieces['fragment'] : '';
            // Remove the base_href to make link relative to root.
            $new_href = str_ireplace(rtrim($base_href, '/'), '', $new_href);

            $file_link->attr($attribute, $new_href);
          }
        }
      }
    }
  }

  /**
   * Convert all Relative HREFs in queryPath to Absolute.
   *
   * @param string $url
   *   Base URL.
   * @param string $destination_base_url
   *   Destination Base URL.
   */
  public function convertLinksAbsoluteSimple($url, $destination_base_url) {
    $url_pieces = parse_url($url);
    $path = $url_pieces['path'];
    $base_for_relative = $url_pieces['scheme'] . '://' . $url_pieces['host'];

    Url::rewriteImageHrefsOnPage($this->queryPath, [], $path, $base_for_relative, $destination_base_url);
    Url::rewriteAnchorHrefsToBinaryFiles($this->queryPath, [], $path, $base_for_relative, $destination_base_url);
    Url::rewriteScriptSourcePaths($this->queryPath, [], $path, $base_for_relative, $destination_base_url);
    Url::rewriteAnchorHrefsToPages($this->queryPath, [], $path, $base_for_relative, $destination_base_url);
  }

  /**
   * Clean extra tags from beginning/end/both of selector contents.
   *
   * @param array $tags
   *   Tags to search for.
   * @param string $selector
   *   Selector to clean.
   * @param string $where
   *   Defaults to 'both', accepts 'leading' and 'trailing'
   */
  public function cleanExtraTags(array $tags, $selector, $where = 'both') {
    $element = $this->queryPath->find($selector);
    $html = $element->innerHTML();
    $html = StringTools::superTrim($html);

    if ($where == 'both' || $where == 'leading') {
      $html = preg_replace('#^' . implode('|^', $tags) . '#i', '', $html);
    }
    if ($where == 'both' || $where == 'trailing') {
      $html = preg_replace('#' . implode('$|', $tags) . '$#i', '', $html);
    }
    $element->html($html);
  }

  /**
   * Clean extra BR tags from beginning/end/both of selector contents.
   *
   * @param string $selector
   *   Selector to clean.
   * @param string $where
   *   Defaults to 'both', accepts 'leading' and 'trailing'
   */
  public function cleanExtraBrTags($selector, $where = 'both') {
    // Normalize variations of the br tag.
    // @codingStandardsIgnoreStart
    $search = [
      '<br>',
      '<br />',
      '<br/>',
    ];
    // @codingStandardsIgnoreEnd
    self::cleanExtraTags($search, $selector, $where);
  }

}
