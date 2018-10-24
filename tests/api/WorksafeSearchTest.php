<?php

namespace Worksafe\Tests\Api;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use Tests\Api\BaseApiTest;

class WorksafeSearchTest extends BaseApiTest {

  protected $contentPath;

  protected $recordPath;

  protected $data;

  protected function doRequest(string $end_point, array $params) : array {

    if ($end_point == 'recordPath') {
      $params['_format'] = 'json';
    }

    $response = $this->client->get($this->domain . $this->{$end_point} . '?' . http_build_query($params));

    $this->assertEquals(200, $response->getStatusCode());

    return $this->getBody($response);
  }

  public function setUp() {
    parent::setup();

    $this->contentPath = '/api/search-content';

    $this->recordPath = '/api/search-record';

    $this->data = [
      // XSS filter.
      [
        'test' => [
          'title' => 'event',
          'query' => 'test<script>alert("hi");</script>',
        ],
        'assert' => [
          'meta' => [
            'query' => 'testalert("hi");',
          ]
        ]
      ],

      // Paging (invalid).
      [
        'test' => [
          'title' => 'event',
          'query' => 'test',
          'rows' => 'not-valid',
          'offset' => 'not-valid',
        ],
        'assert' => [
          'meta' => [
            'paging' => [
              'limit' => 0,
              'offset' => 0,
            ],
          ]
        ]
      ],

      // Paging (valid).
      [
        'test' => [
          'title' => 'event',
          'query' => 'test',
          'rows' => 15,
          'offset' => 30,
        ],
        'assert' => [
          'meta' => [
            'paging' => [
              'limit' => 15,
              'offset' => 30,
            ],
          ]
        ]
      ],

      // Sort (valid).
      [
        'test' => [
          'title' => 'event',
          'query' => 'test',
          'sort' => 'title,industry',
          'order' => 'desc',
        ],
        'assert' => [
          'meta' => [
            'sort' => [
              'field' => 'title,industry',
              'order' => 'desc',
            ],
          ]
        ]
      ],

      // Sort (invalid).
      [
        'test' => [
          'title' => 'event',
          'query' => 'test',
          'sort' => 'non-existing-field',
          'order' => 'desc',
        ],
        'assert' => []
      ],

      // Filter (valid).
      [
        'test' => [
          'title' => 'event',
        ],
        'assert' => [
          'meta' => [
            'filters' => [
              'title' => 'event',
            ]
          ]
        ]
      ],

      // Filter (invalid).
      [
        'test' => [
          'non-existing' => 'event',
        ],
        'assert' => [
          'meta' => []
        ]
      ],

      // Date (valid).
      [
        'test' => [
          'event_date' => '2018-07-15,2018-07-30',
        ],
        'assert' => [
          'meta' => [
            'filters' => [
              'event_date' => '2018-07-15,2018-07-30',
            ]
          ]
        ]
      ],

      // Date (invalid).
      [
        'test' => [
          'event_date' => '2018-07-15,2018-07-32',
        ],
        'assert' => [
          'meta' => []
        ]
      ],

    ];
  }

  public function testParam() {
    foreach ($this->data as $d) {
      // Test both end points.
      foreach (['contentPath', 'recordPath'] as $path) {
        $response = $this->doRequest($path, $d['test']);
        $this->assertArraySubset($d['assert'], $response);
      }
    }
  }
}
