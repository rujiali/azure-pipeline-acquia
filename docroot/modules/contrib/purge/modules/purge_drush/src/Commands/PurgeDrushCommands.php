<?php
namespace Drupal\purge_drush\Commands;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\purge\Logger\LoggerServiceInterface;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\TypeUnsupportedException;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvStatesInterface;
use Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueService;
use Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface;
use Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticsServiceInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 *
 * In addition to a commandfile like this one, you need a drush.services.yml
 * in root of your module.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class PurgeDrushCommands extends DrushCommands {

  /**
   * @var \Drupal\purge\Logger\LoggerServiceInterface
   */
  protected $purgeLogger;

  /**
   * @var \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface
   */
  protected $purgers;

  /**
   * @var \Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface
   */
  protected $processors;

  /**
   * @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface
   */
  protected $invalidationFactory;

  /**
   * @var \Drupal\purge\Plugin\Purge\Queue\QueueService
   */
  protected $purgeQueue;

  /**
   * @var \Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface
   */
  protected $queueStats;

  /**
   * @var \Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface
   */
  protected $purgeQueuers;

  /**
   * @var \Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticsServiceInterface
   */
  protected $diagnostics;

  /**
   * PurgeDrushCommands constructor.
   *
   * @param LoggerServiceInterface $purgeLogger
   * @param InvalidationsServiceInterface $invalidationFactory
   * @param ProcessorsServiceInterface $processorsService
   * @param PurgersServiceInterface $purgersService
   * @param QueueService $purgeQueue
   * @param StatsTrackerInterface $queueStats
   * @param QueuersServiceInterface $purgeQueuers
   * @param DiagnosticsServiceInterface $diagnostics
   *   The purge diagnostics service.
   */
  public function __construct(
    LoggerServiceInterface $purgeLogger,
    InvalidationsServiceInterface $invalidationFactory,
    ProcessorsServiceInterface $processorsService,
    PurgersServiceInterface $purgersService,
    QueueService $purgeQueue,
    StatsTrackerInterface $queueStats,
    QueuersServiceInterface $purgeQueuers,
    DiagnosticsServiceInterface $diagnostics
  ) {
    parent::__construct();
    $this->purgeLogger = $purgeLogger;
    $this->invalidationFactory = $invalidationFactory;
    $this->processors = $processorsService;
    $this->purgers = $purgersService;
    $this->purgeQueue = $purgeQueue;
    $this->queueStats = $queueStats;
    $this->purgeQueuers = $purgeQueuers;
    $this->diagnostics = $diagnostics;
  }

  /**
   * Disable debugging for all of Purge's log channels.
   *
   * @command p:debug:dis
   *
   * @usage drush p-debug-disable
   *   Disables the log channels.
   *
   * @aliases pddis,p-debug-dis,p-debug-disable
   *
   * @throws \LogicException
   *
   * @return array|void
   */
  public function debugDis() {
    $channels = function() {
      $ids = [];

      foreach ($this->purgeLogger->getChannels() as $channel) {
        if (in_array(RfcLogLevel::DEBUG, $channel['grants'])) {
          $ids[] = $channel['id'];
        }
      }

      return $ids;
    };

    $disable = function() {
      foreach ($this->purgeLogger->getChannels() as $channel) {
        if (in_array(RfcLogLevel::DEBUG, $channel['grants'])) {
          $key = array_search(RfcLogLevel::DEBUG, $channel['grants']);
          unset($channel['grants'][$key]);
          $this->purgeLogger->setChannel($channel['id'], $channel['grants']);
        }
      }
    };

    if (empty($channels())) {
      $this->logger()->warning(dt('Debugging already disabled for all channels.'));
      return;
    }

    // Present the user with some help and a conformation message.
    $this->output()->writeln('Disabling debug logging for the following log channels:');

    foreach ($channels() as $id) {
      $this->output()->writeln(' - ' . $id);
    }

    $disable();
    $this->logger()->success(dt('Done!'));
  }

  /**
   * Enable debugging for all of Purge's log channels.
   *
   * @command p:debug:en
   *
   * @usage drush p-debug-enable
   *   Enables the log channels.
   *
   * @throws \LogicException
   * @throws UserAbortException
   *
   * @aliases pden,p-debug-en,p-debug-enable
   *
   * @return array|void
   */
  public function debugEn() {
    $channels = function() {
      $ids = [];

      foreach ($this->purgeLogger->getChannels() as $channel) {
        if (!in_array(RfcLogLevel::DEBUG, $channel['grants'])) {
          $ids[] = $channel['id'];
        }
      }

      return $ids;
    };

    $enable = function() {
      foreach ($this->purgeLogger->getChannels() as $channel) {
        if (!in_array(RfcLogLevel::DEBUG, $channel['grants'])) {
          $channel['grants'][] = RfcLogLevel::DEBUG;
          $this->purgeLogger->setChannel($channel['id'], $channel['grants']);
        }
      }
    };

    if (empty($channels())) {
      $this->logger()->warning(dt('Debugging already enabled for all channels.'));
      return;
    }

    // Present the user with some help and a conformation message.
    $this->output()->writeln('About to enable debugging for the following log channels:');

    foreach ($channels() as $id) {
      $this->output()->writeln(' - ' . $id);
    }

    $this->output()->writeln("\nOnce enabled, this allows you to run Drush commands like"
      . ' p-queue-work with the -v parameter, giving you a detailed'
      . ' amount of live-debugging information getting logged by Purge'
      . ' and modules integrating with it.'
      . ' HOWEVER, debug logging is VERY verbose and can add'
      . ' millions of messages when left enabled for too long. NEVER'
      . ' enable this on a production environment without fully'
      . " understanding the consequences!\n"
    );

    if ($this->io()->confirm('Are you sure you want to enable it?')) {
      $enable();
      $this->logger()->success("Enabled! Use p-debug-dis to disable when you're finished!");
    }
    else {
      throw new UserAbortException();
    }
  }

  /**
   * Generate a diagnostic self-service report.
   *
   * @command p:diagnostics
   *
   * @usage drush p-diagnostics
   *   Build the diagnostic report as a table.
   * @usage drush p-diagnostics --format=json
   *   Export as JSON.
   * @usage drush p-diagnostics --format=yaml
   *   Export as YAML.
   *
   * @aliases pdia,p-diagnostics
   *
   * @return array
   */
  public function diagnostics() {
    $output = [];

    foreach ($this->diagnostics as $check) {
      $output[] = [
        'id' => (string) $check->getPluginId(),
        'title' => (string) $check->getTitle(),
        'value' => (string) $check->getValue(),
        'severity_int' => $check->getSeverity(),
        'severity' => (string) $check->getSeverityString(),
        'description' => (string) $check->getDescription(),
        'recommendation' => (string) $check->getRecommendation(),
        'blocks_processing' => $check->getSeverity() === DiagnosticCheckInterface::SEVERITY_ERROR,
      ];
    }

    return $output;
  }

  /**
   * Directly invalidate an item without going through the queue.
   *
   * @command p:invalidate
   *
   * @param $type
   *   The type of invalidation to perform, e.g.: tag, path, url.
   * @param $expression
   *   The string expression of what needs to be invalidated.
   *
   * @usage drush p-invalidate tag node:1
   *   Clears URLs tagged with "node:1" from external caching platforms.
   * @usage drush p-invalidate url http://www.drupal.org/
   *   Clears "http://www.drupal.org/" from external caching platforms.
   * @usage drush p-invalidate everything
   *   Clears everything on external caching platforms.
   *
   * @aliases pinv,p-invalidate
   *
   * @throws \Exception
   *   When a drush error occurs.
   * @throws UserAbortException
   *   When the user aborts the command.
   *
   * @return void
   */
  public function invalidate($type, $expression = NULL) {
    // Retrieve our queuer object and fail when it is not returned.
    if (!($processor = $this->processors->get('drush_purge_invalidate'))) {
      throw new \Exception(dt('Not authorized, processor missing!'));
    }

    // Instantiate the invalidation object based on user input.
    try {
      $invalidations = [$this->invalidationFactory->get($type, $expression)];
    }
    catch (PluginNotFoundException $e) {
      throw new \Exception(dt("Type '@type' does not exist, see 'drush p-types' for available types.", ['@type' => $type]));
    }
    catch (TypeUnsupportedException $e) {
      throw new \Exception(dt("There is no purger supporting '@type', please install one!", ['@type' => $type]));
    }
    catch (\Exception $e) {
      throw new \Exception($e->getMessage());
    }

    // Prevent users from accidentally harming their website.
    if ($type === 'everything') {
      $this->output()->writeln('Invalidating everything will mass-clear potentially thousands'
        . ' of pages, which could temporarily make your site really slow as'
        . " external caches will have to warm up again.\n");
      if (!$this->io()->confirm('Are you really sure?')) {
        throw new UserAbortException();
      }
    }

    // Attempt the cache invalidation. Exceptions will be thrown when errors
    // occur.
    try {
      $this->purgers->invalidate($processor, $invalidations);
    }
    catch (\Exception $e) {
      throw new \Exception($e->getMessage());
    }

    // Since this command is more meant for testing, we only regard SUCCEEDED as
    // a acceptable return state to call success on.
    if ($invalidations[0]->getState() === InvStatesInterface::SUCCEEDED) {
      $this->logger()->success(dt('Item invalidated successfully!'));
      return;
    }

    $this->logger()->error(dt('Invalidation failed, its return state is: @state.', [
      '@state' => $invalidations[0]->getStateString()
    ]));
  }

  /**
   * Add a new processor.
   *
   * @command p:processor:add
   *
   * @param $id
   *   The plugin ID of the processor to add.
   *
   * @usage drush p-processor-add ID
   *   Add a processor of type ID.
   *
   * @aliases pradd,p-processor-add
   *
   * @throws \Exception
   *   When a drush error occurs.
   *
   * @return void
   */
  public function processorAdd($id) {
    $enabled = $this->processors->getPluginsEnabled();

    // Verify that the plugin exists.
    if (!isset($this->processors->getPlugins()[$id])) {
      throw new \Exception(dt('The given plugin does not exist!'));
    }

    // Verify that the plugin is available and thus not yet enabled.
    if (!in_array($id, $this->processors->getPluginsAvailable())) {
      $this->logger()->warning(dt('This processor is already enabled!'));
      return;
    }

    // Define the new instance and store it.
    $enabled[] = $id;
    $this->processors->setPluginsEnabled($enabled);
    $this->logger()->success(dt('The processor has been added!'));
  }

  /**
   * List all enabled processors.
   *
   * @command p:processor:ls
   *
   * @usage drush p-processor-ls
   *   List all processors in a table.
   *
   * @aliases prls,p-processor-ls
   *
   * @return array
   */
  public function processorLs() {
    $output = [];

    foreach ($this->processors as $processor) {
      $plugin_id = $processor->getPluginId();
      $output[$plugin_id] = [
        'id' => $plugin_id,
      ];
    }

    return $output;
  }

  /**
   * List available processor plugin IDs that can be added.
   *
   * @command p:processor:lsa
   *
   * @usage drush p-processor-lsa
   *   List available plugin IDs for which processors can be created.
   *
   * @aliases prlsa,p-processor-lsa
   *
   * @return array
   */
  public function processorLsa() {
    $available = $this->processors->getPluginsAvailable();
    $output = [];

    foreach ($available as $plugin_id) {
      $output[$plugin_id] = [
        'plugin_id' => $plugin_id,
      ];
    }

    return $output;
  }

  /**
   * Remove a processor.
   *
   * @command p:processor:rm
   *
   * @param $id
   *   The plugin ID of the processor to remove.
   *
   * @usage drush p-processor-rm ID
   *   Remove the given processor.
   *
   * @aliases prrm,p-processor-rm
   *
   * @throws \Exception
   *   When a drush error occurs.
   */
  public function processorRm($id) {
    $enabled = $this->processors->getPluginsEnabled();

    // Verify that the processor exists.
    if (!in_array($id, $enabled)) {
      throw new \Exception(dt('The given plugin ID is not valid!'));
    }

    // Remove the processor and finish command execution.
    unset($enabled[array_search($id, $enabled)]);
    $this->processors->setPluginsEnabled($enabled);

    $this->logger()->success(dt('The processor has been removed!'));
  }

  /**
   * Create a new purger instance.
   *
   * @command p:purger:add
   *
   * @param $id
   *   The plugin ID of the purger instance to create.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option if-not-exists Don't create a new purger if one of this type exists.
   *
   * @usage drush p-purger-add ID
   *   Add a purger of type ID.
   * @usage drush p-purger-add --if-not-exists ID
   *   Create purger ID if it does not exist.
   *
   * @aliases ppadd,p-purger-add
   *
   * @throws \Exception
   *   When a drush error occurs.
   *
   * @return void
   */
  public function purgerAdd($id, array $options = ['if-not-exists' => NULL]) {
    $enabled = $this->purgers->getPluginsEnabled();

    // Verify that the plugin exists.
    if (!isset($this->purgers->getPlugins()[$id])) {
      throw new \Exception(dt('The given plugin does not exist!'));
    }

    // When --if-not-exists is passed, we cancel creating double purgers.
    if ($options['if-not-exists']) {
      if (in_array($id, $enabled)) {
        $this->logger()->warning(dt('The purger already exists!'));
        return;
      }
    }

    // Verify that new instances of the plugin may be created.
    if (!in_array($id, $this->purgers->getPluginsAvailable())) {
      throw new \Exception(dt('No more instances of this plugin can be created!'));
    }

    // Define the new instance and store it.
    $enabled[$this->purgers->createId()] = $id;
    $this->purgers->setPluginsEnabled($enabled);

    $this->logger()->success(dt('The purger has been created!'));
  }

  /**
   * List all configured purgers in order of execution.
   *
   * @command p:purger:ls
   *
   * @usage drush p-purger-ls
   *   List all configured purgers in order of execution.
   *
   * @aliases ppls,p-purger-ls
   *
   * @return array
   */
  public function purgerLs() {
    $enabled = $this->purgers->getPluginsEnabled();
    $output = [];

    if (!empty($enabled)) {
      $output['headers'] = [
        'instance_id' => '<info>' . dt('Instance ID') . '</info>',
        'plugin_id' => '<info>' . dt('Plugin ID') . '</info>',
      ];
    }

    foreach ($enabled as $instance_id => $plugin_id) {
      $output[$instance_id] = [
        'instance_id' => $instance_id,
        'plugin_id' => $plugin_id,
      ];
    }

    return $output;
  }

  /**
   * List available plugin IDs for which purgers can be added.
   *
   * @command p:purger:lsa
   *
   * @usage drush p-purger-lsa
   *   List available plugin IDs for which purgers can be created.
   *
   * @aliases pplsa,p-purger-lsa
   *
   * @return array
   */
  public function purgerLsa() {
    $available = $this->purgers->getPluginsAvailable();
    $output = [];

    foreach ($available as $plugin_id) {
      $output[$plugin_id] = [
        'plugin_id' => $plugin_id,
      ];
    }

    return $output;
  }

  /**
   * Move the given purger DOWN in the execution order.
   *
   * @command p:purger:mvd
   *
   * @param $id
   *   The instance ID of the purger to move down.
   *
   * @usage drush p-purger-mv-down ID
   *   Move this purger down.
   *
   * @aliases ppmvd,p-purger-mvd,p-purger-mv-down
   *
   * @throws \Exception
   *   When a drush error occurs.
   *
   * @return bool|string
   */
  public function purgerMvd($id) {
    $enabled = $this->purgers->getPluginsEnabled();

    // Verify that the purger instance exists.
    if (!isset($enabled[$id])) {
      throw new \Exception(dt('The given instance ID is not valid!'));
    }

    // Move the purger down and finish command execution.
    $this->purgers->movePurgerDown($id);

    $this->logger()->success(dt('The purger moved one place down!'));
  }

  /**
   * Move the given purger UP in the execution order.
   *
   * @command p:purger:mvu
   *
   * @param $id
   *   The instance ID of the purger to move up.
   *
   * @usage drush p-purger-mv-up ID
   *   Move this purger up.
   *
   * @aliases ppmvu,p-purger-mvu,p-purger-mv-up
   *
   * @throws \Exception
   *   When a drush error occurs.
   *
   * @return bool|string
   */
  public function purgerMvu($id) {
    $enabled = $this->purgers->getPluginsEnabled();

    // Verify that the purger instance exists.
    if (!isset($enabled[$id])) {
      throw new \Exception(dt('The given instance ID is not valid!'));
    }

    // Move the purger up and finish command execution.
    $this->purgers->movePurgerUp($id);

    $this->logger()->success(dt('The purger moved one place up!'));
  }

  /**
   * Remove a purger instance.
   *
   * @command p:purger:rm
   *
   * @param $id
   *   The instance ID of the purger to remove.
   *
   * @usage drush p-purger-rm ID
   *   Remove the given purger.
   *
   * @aliases pprm,p-purger-rm
   *
   * @throws \Exception
   *   When a drush error occurs.
   *
   * @return bool|string
   */
  public function purgerRm($id) {
    $enabled = $this->purgers->getPluginsEnabled();

    // Verify that the purger instance exists.
    if (!isset($enabled[$id])) {
      throw new \Exception(dt('The given instance ID is not valid!'));
    }

    // Remove the purger instance and finish command execution.
    unset($enabled[$id]);
    $this->purgers->setPluginsEnabled($enabled);

    $this->logger()->success(dt('The purger has been removed!'));
  }

  /**
   * Add one or more items to the queue for later processing.
   *
   * @command p:queue:add
   *
   * @param string ...
   *   Parameters are expected to be in the format "<TYPE> <EXPRESSION>"
   *   and can contain commas to separate extra items, in the same format.
   *   - Type: The type of invalidation to queue, e.g.: tag, path, url.
   *   - Expression: The string expression of what needs to be invalidated.
   *
   * @usage drush p-queue-add "tag node:1"
   *   Clears all cached pages matching TAG "node:1".
   * @usage drush pqa "url http://www.s.com/rss.xml"
   *   Clears only the URL provided.
   * @usage drush pqa "wildcardurl http://s.com/f/*"
   *   Clears URLs by wildcard, all under http://s.com/f/ will be cleared.
   * @usage drush pqa everything
   *   Instructs to clear the entire site, be careful!
   * @usage drush pqa tag node:1,tag node:2,url http://../rss.xml,tag node:321
   *   Comma separated input of multiple items.
   *
   * @aliases pqa,p-queue-add
   *
   * @throws \Exception
   *   When a drush error occurs.
   */
  public function queueAdd() {
    // Retrieve our queuer object and fail when it is not returned.
    if (!$queuer = $this->purgeQueuers->get('drush_purge_queue_add')) {
      throw new \Exception(dt('Not authorized, queuer missing!'));
    }

    // Clean input and parse comma-separated input items.
    $items = func_get_args();
    array_pop($items);
    $items = array_map('trim', explode(',', implode(' ', $items)));

    array_walk($items, function(&$value, $key) {
      $value = explode(' ', $value);

      if (!isset($value[1])) {
        $value[1] = NULL;
      }
    });

    // Iterate the provided input and provide feedback to the user.
    $invalidations = [];

    foreach ($items as $i => list($type, $expression)) {
      if (NULL === $type || empty($type)) {
        unset($items[$i]);
        continue;
      }

      // Instantiate the invalidation object based on user input.
      try {
        $invalidations[] = $this->invalidationFactory->get($type, $expression);
      }
      catch (PluginNotFoundException $e) {
        throw new \Exception(dt("Type '@type' does not exist, see 'drush p-types' for available types.", ['@type' => $type]));
      }
      catch (TypeUnsupportedException $e) {
        throw new \Exception(dt("There is no purger supporting '@type', please install one!", ['@type' => $type]));
      }
      catch (\Exception $e) {
        throw new \Exception($e->getMessage());
      }

      // Prevent users from accidentally harming their website.
      if ($type === 'everything') {
        $this->output()->writeln('Invalidating everything will mass-clear potentially'
          . ' thousands of pages, which could temporarily make your site really'
          . " slow as external caches will have to warm up again.\n");

        if (!$this->io()->confirm('Are you really sure?')) {
          throw new UserAbortException();
        }
      }
    }

    // Add the objects to the queue and give user feedback.
    $this->purgeQueue->add($queuer, $invalidations);

    $this->logger()->success(dt('Added @count item(s) to the queue.', ['@count' => count($invalidations)]));
  }

  /**
   * Inspect what is in the queue by paging through it.
   *
   * @command p:queue:browse
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   *
   * @option limit
   *   The number of items to show on a single page.
   * @option page
   *   The page to show data for, pages start at 1.
   *
   * @usage drush p-queue-browse
   *   Browse queue content and press space to load more.
   * @usage drush p-queue-browse --limit=30
   *   Browse the queue content and show 30 items at a time.
   * @usage drush p-queue-browse --page=3
   *   Show page 3 of the queue.
   *
   * @aliases pqb,p-queue-browse
   *
   * @throws \Exception
   *   When a drush error occurs.
   *
   * @return array
   *
   * @todo Add browse functionality, see Drush 8 implementation.
   */
  public function queueBrowse(array $options = ['limit' => 30, 'page' => 1]) {
    $output = [];

    foreach ($this->purgeQueue->selectPage((int) $options['page']) as $immutable) {
      $exp = $immutable->getExpression();
      $output[$exp][] = $exp;
    }

    return $output;
  }

  /**
   * Empty the entire queue.
   *
   * @command p:queue:empty
   *
   * @usage drush p-queue-empty
   *   Empty the entire queue.
   *
   * @aliases pqe,p-queue-empty
   */
  public function queueEmpty() {
    $total = (int) $this->queueStats->numberOfItems()->get();
    $this->purgeQueue->emptyQueue();

    if ($total !== 0) {
      $this->logger()->success(dt('Cleared @total items from the queue.', ['@total' => $total]));
      return;
    }

    $this->logger()->notice(dt('The queue was empty, nothing to clear!'));
  }

  /**
   * Retrieve the queue statistics.
   *
   * @command p:queue:stats
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option reset-totals
   *   Wipe the TOTAL statistical counters.
   *
   * @usage drush p-queue-stats
   *   Retrieve the queue statistics.
   * @usage drush p-queue-stats --reset-totals
   *   Wipe the TOTAL statistical counters.
   *
   * @aliases pqs,p-queue-stats
   *
   * @throws UserAbortException
   *
   * @return array
   */
  public function queueStats(array $options = ['reset-totals' => NULL]) {
    // Reset the total counters if requested to.
    if ($options['reset-totals']) {
      $this->output()->writeln("You are about to reset all total counters...\n");

      if ($this->io()->confirm('Are you really sure?')) {
        $this->queueStats->resetTotals();
        $this->logger()->success('Done!');
        return;
      }
      else {
        throw new UserAbortException();
      }
    }

    // Normal output generation.
    $table = [];
    $align_right = function($input, $size = 20) {
      $this->output()->writeln(str_repeat(' ', $size - strlen($input)) . $input);
    };

    foreach ($this->queueStats as $statistic) {
      $table[] = [
        'left' => $align_right(strtoupper($statistic->getId())),
        'right' => $statistic->getTitle()
      ];
      $table[] = [
        'left' => $align_right($statistic->getInteger()),
        'right' => '',
      ];
      $table[] = ['left' => '', 'right' => $statistic->getDescription()];
      $table[] = ['left' => '', 'right' => ''];
    }

    return $table;
  }

  /**
   * Count how many items currently sit in the queue.
   *
   * @command p:queue:volume
   *
   * @usage drush p-queue-volume
   *   The number of items in the queue.
   *
   * @aliases pqv,p-queue-volume
   */
  public function queueVolume() {
    $volume = (int) $this->purgeQueue->numberOfItems();
    $this->output()->writeln(dt('There are @total items in the queue.', ['@total' => $volume]));
  }

  /**
   * Claim a chunk of items from the queue and process them.
   *
   * @command p:queue:work
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option finish
   *   Continue processing till the queue is empty.
   *
   * @usage drush p-queue-work
   *   Claim a chunk of items from the queue and process them.
   *
   * @aliases pqw,p-queue-work
   *
   * @throws \Exception
   *   When a drush error occurs.
   */
  public function queueWork(array $options = ['finish' => NULL]) {
    // Retrieve our queuer object and fail when it is not returned.
    if (!($processor = $this->processors->get('drush_purge_queue_work'))) {
      throw new \Exception('Not authorized, processor missing!');
    }

    // In finish mode, we'll fork ourselves until the entire queue is empty.
    if ($options['finish']) {
      if ($this->purgeQueue->numberOfItems() < 1) {
        throw new \Exception('No items can be claimed from the queue.');
      }

      // Create the arguments list. Silence subprocesses in boolean mode.
      $arguments = ['@self', 'p-queue-work', [], ['format' => $options['format']]];

      // Iterate until the queue is empty and collect return values.
      $returns = [];

      while ($this->purgeQueue->numberOfItems() > 0 && !in_array(FALSE, $returns, TRUE)) {
        $cmd = drush_invoke_process(...$arguments);

        if ($cmd['error_status']) {
          $cmd['object'] = FALSE;
        }

        if (is_array($cmd['object']) && empty($cmd['object'])) {
          $cmd['object'] = FALSE;
        }

        $returns[] = $cmd['object'];
      }

      $this->logger()->success('Finished.');
    }

    // Single chunk processing mode.
    else {
      $claims = $this->purgeQueue->claim();
      // Attempt the cache invalidation and deal with errors.
      try {
        $this->purgers->invalidate($processor, $claims);
      }
      catch (\Exception $e) {
        throw new \Exception($e->getMessage());
      }
      finally {
        $this->purgeQueue->handleResults($claims);
      }

      // Evaluate all claim states to booleans and collect the results. Then
      // return the overall outcome, which is FALSE if one failed.
      $results = [];

      foreach ($claims as $claim) {
        if (in_array($claim->getStateString(), ['PROCESSING', 'SUCCEEDED'])) {
          $results['success'][] = TRUE;
        }
        else {
          $results['error'][] = TRUE;
        }
      }

      if (isset($results['success'])) {
        $this->logger()->success('Processed ' . count($results['success']) . ' objects.');
      }

      if (isset($results['error'])) {
        $this->logger()->error('Failed to process ' . count($results['error']) . ' objects.');
      }
    }
  }

  /**
   * Add a new queuer.
   *
   * @command p:queuer:add
   *
   * @param $id
   *   The plugin ID of the queuer to add.
   *
   * @usage drush p-queuer-add ID
   *   Add a queuer of type ID.
   *
   * @aliases puadd,p-queuer-add
   *
   * @throws \Exception
   *   When a drush error occurs.
   */
  public function queuerAdd($id) {
    $enabled = $this->purgeQueuers->getPluginsEnabled();

    // Verify that the plugin exists.
    if (!isset($this->purgeQueuers->getPlugins()[$id])) {
      throw new \Exception('The given plugin does not exist!');
    }

    // Verify that the plugin is available and thus not yet enabled.
    if (!in_array($id, $this->purgeQueuers->getPluginsAvailable())) {
      $this->logger()->warning(dt('This queuer is already enabled!'));
      return;
    }

    // Define the new instance and store it.
    $enabled[] = $id;
    $this->purgeQueuers->setPluginsEnabled($enabled);

    $this->logger()->success(dt('The queuer has been added!'));
  }

  /**
   * List all enabled queuers.
   *
   * @command p:queuer:ls
   *
   * @usage drush p-queuer-ls
   *   List all queuers in a table.
   *
   * @aliases puls,p-queuer-ls
   *
   * @return array
   */
  public function queuerLs() {
    $output = [];

    foreach ($this->purgeQueuers as $queuer) {
      $output[]['id'] = $queuer->getPluginId();
    }

    return $output;
  }

  /**
   * List available queuer plugin IDs that can be added.
   *
   * @command p:queuer:lsa
   *
   * @usage drush p-queuer-lsa
   *   List available plugin IDs for which queuers can be created.
   *
   * @aliases pulsa,p-queuer-lsa
   *
   * @return array
   */
  public function queuerLsa() {
    $available = $this->purgeQueuers->getPluginsAvailable();
    $output = [];

    foreach ($available as $plugin_id) {
      $output[$plugin_id]['plugin_id'] = $plugin_id;
    }

    return $output;
  }

  /**
   * Remove a queuer.
   *
   * @command p:queuer:rm
   *
   * @param $id
   *   The plugin ID of the queuer to remove.
   *
   * @usage drush p-queuer-rm ID
   *   Remove the given queuer.
   *
   * @aliases purm,p-queuer-rm
   *
   * @throws \Exception
   *   When a drush error occurs.
   */
  public function queuerRm($id) {
    $enabled = $this->purgeQueuers->getPluginsEnabled();

    // Verify that the queuer exists.
    if (!in_array($id, $enabled)) {
      throw new \Exception('The given plugin ID is not valid!');
    }

    // Remove the queuer and finish command execution.
    unset($enabled[array_search($id, $enabled)]);
    $this->purgeQueuers->setPluginsEnabled($enabled);

    $this->logger()->success(dt('The queuer has been removed!'));
  }

  /**
   * List all supported cache invalidation types.
   *
   * @command p:types
   *
   * @usage drush p-types
   *   List all supported cache invalidation types.
   *
   * @aliases ptyp,p-types
   *
   * @return array
   */
  public function types() {
    $output = [];

    // Return a simple listing of supported types.
    foreach ($this->purgers->getTypes() as $type) {
      $output[$type]['type'] = $type;
    }

    return $output;
  }

  /**
   * PurgeDrushCommands destructor.
   *
   * Drush 9 is not triggering the PostResponseEvent which should execute the
   * destruct method of the objects called below. In awaiting of a decent
   * solution, we do it manually.
   *
   * @todo Remove this method as soon as these services are destructable using
   *       the KernelDestructionSubscriber.
   *
   * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21EventSubscriber%21KernelDestructionSubscriber.php/class/KernelDestructionSubscriber/8.5.x
   */
  public function __destruct() {
    $this->purgeLogger->destruct();
    $this->purgeQueue->destruct();
    $this->queueStats->destruct();
  }

}
