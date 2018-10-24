<?php

namespace Drupal\entity_print\Renderer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\entity_print\Asset\AssetRendererInterface;
use Drupal\entity_print\Event\PrintEvents;
use Drupal\entity_print\Event\PrintHtmlAlterEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Render\RendererInterface as CoreRendererInterface;

/**
 * The RendererBase class.
 */
abstract class RendererBase implements RendererInterface {

  /**
   * The filename used when we're unable to calculate a filename.
   *
   * @var string
   */
  const DEFAULT_FILENAME = 'document';

  /**
   * The renderer for renderable arrays.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The asset renderer.
   *
   * @var \Drupal\entity_print\Asset\AssetRendererInterface
   */
  protected $assetRenderer;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  public function __construct(CoreRendererInterface $renderer, AssetRendererInterface $asset_renderer, EventDispatcherInterface $event_dispatcher) {
    $this->renderer = $renderer;
    $this->assetRenderer = $asset_renderer;
    $this->dispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function generateHtml(array $entities, array $render, $use_default_css, $optimize_css) {
    $rendered_css = $this->assetRenderer->render($entities, $use_default_css, $optimize_css);
    $render['#entity_print_css'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$rendered_css) {
      return $this->renderer->render($rendered_css);
    });

    $html = (string) $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$render) {
      return $this->renderer->render($render);
    });

    // Allow other modules to alter the generated HTML.
    $this->dispatcher->dispatch(PrintEvents::POST_RENDER, new PrintHtmlAlterEvent($html, $entities));

    return $html;
  }

  /**
   * Gets a safe filename.
   *
   * @param string $filename
   *   The un-processed filename.
   *
   * @return string
   *   The filename stripped to only safe characters.
   */
  protected function sanitizeFilename($filename) {
    return preg_replace("/[^A-Za-z0-9 ]/", '', $filename);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(array $entities) {
    $filenames = [];
    foreach ($entities as $entity) {
      if ($label = trim($this->sanitizeFilename($this->getLabel($entity)))) {
        $filenames[] = $label;
      }
    }
    return $filenames ? implode('-', $filenames) : static::DEFAULT_FILENAME;
  }

  /**
   * Gets the entity label.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we want to generate a label for.
   *
   * @return string
   *   The label for this entity.
   */
  abstract protected function getLabel(EntityInterface $entity);

}
