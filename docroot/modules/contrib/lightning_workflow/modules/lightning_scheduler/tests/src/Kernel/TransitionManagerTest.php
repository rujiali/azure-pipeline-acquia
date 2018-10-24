<?php

namespace Drupal\Tests\lightning_scheduler\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lightning_scheduler\TransitionManager;

/**
 * @coversDefaultClass \Drupal\lightning_scheduler\TransitionManager
 *
 * @group lightning
 * @group lightning_workflow
 * @group lightning_scheduler
 */
class TransitionManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'datetime',
    'lightning_scheduler',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig('system');

    // In order to prove that time zones are normalized correctly, set the
    // system default and Drupal default time zones differently.
    date_default_timezone_set('UTC');
    $this->config('system.date')
      ->set('timezone.default', 'America/New_York')
      ->save();
  }

  /**
   * @covers ::validate
   *
   * @dataProvider providerValidate
   */
  public function testValidate($value, $expect_errors) {
    $element = [
      '#value' => Json::encode($value),
      '#name' => 'test_element',
      '#parents' => ['test_element'],
    ];
    $form_state = new FormState();

    $form_state->setFormObject($this->prophesize(FormInterface::class)->reveal());

    TransitionManager::validate($element, $form_state);

    $this->assertSame($expect_errors, FormState::hasAnyErrors());
  }

  /**
   * Data provider for ::testValidate().
   *
   * @return array
   */
  public function providerValidate() {
    return [
      'empty string' => [
        '',
        TRUE,
      ],
      'null' => [
        NULL,
        TRUE,
      ],
      'boolean false' => [
        FALSE,
        TRUE,
      ],
      'boolean true' => [
        TRUE,
        TRUE,
      ],
      'random string' => [
        $this->randomString(128),
        TRUE,
      ],
      'integer' => [
        123,
        TRUE,
      ],
      'empty array' => [
        [],
        FALSE,
      ],
      'valid time, missing date' => [
        [
          'when' => '08:57',
        ],
        TRUE,
      ],
      'valid date, missing time' => [
        [
          [
            'state' => 'fubar',
            'when' => '1984-09-19',
          ],
        ],
        FALSE,
      ],
      'valid time, invalid date' => [
        [
          [
            'when' => '1938-37-12 08:57',
          ],
        ],
        TRUE,
      ],
      'valid date, invalid time' => [
        [
          [
            'when' => '1984-09-19 26:39',
          ],
        ],
        TRUE,
      ],
      'invalid date and time' => [
        [
          [
            'when' => '1938-37-12 26:39',
          ],
        ],
        TRUE,
      ],
      'valid date and time, invalid order' => [
        [
          [
            'state' => 'fubar',
            'when' => '2018-11-05 15:42',
          ],
          [
            'state' => 'fubar',
            'when' => '2018-09-04 02:30',
          ],
        ],
        TRUE,
      ],
      'valid same dates, valid times, invalid order' => [
        [
          [
            'state' => 'fubar',
            'when' => '2022-09-19 06:30',
          ],
          [
            'state' => 'fubar',
            'when' => '2022-09-19 04:46',
          ],
        ],
        TRUE,
      ],
      'valid different dates' => [
        [
          [
            'state' => 'fubar',
            'when' => '2022-09-04 02:30',
          ],
          [
            'state' => 'fubar',
            'when' => '2022-11-05 15:42',
          ],
        ],
        FALSE,
      ],
      'valid same dates, different times' => [
        [
          [
            'state' => 'fubar',
            'when' => '2022-09-19 02:30',
          ],
          [
            'state' => 'fubar',
            'when' => '2022-09-19 15:42',
          ],
        ],
        FALSE,
      ],
    ];
  }

}
