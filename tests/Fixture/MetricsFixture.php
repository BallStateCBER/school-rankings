<?php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * MetricsFixture
 *
 */
class MetricsFixture extends TestFixture
{
    public const HIDDEN_SCHOOL_METRIC = 11;
    public const HIDDEN_DISTRICT_METRIC = 12;

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'context' => ['type' => 'string', 'length' => 10, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'description' => ['type' => 'text', 'length' => null, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'type' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'parent_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'lft' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'rght' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'selectable' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '0', 'comment' => '', 'precision' => null],
        'visible' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '1', 'comment' => '', 'precision' => null],
        'is_percent' => ['type' => 'boolean', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci'
        ],
    ];

    private $defaultData = [
        'context' => 'school',
        'description' => '',
        'type' => 'numeric',
        'parent_id' => null,
        'selectable' => 1,
        'visible' => 1,
        'is_percent' => 0,
        'created' => '2018-04-03 16:18:09'
    ];

    /**
     * Records
     *
     * @var array
     */
    public $records = [

        // School
        [
            'id' => 1,
            'name' => 'Root-level metric 1',
            'lft' => 1,
            'rght' => 6
        ],
            [
                'id' => 2,
                'name' => 'Child metric',
                'parent_id' => 1,
                'lft' => 2,
                'rght' => 3
            ],
            [
                'id' => 3,
                'name' => 'Identical name',
                'parent_id' => 1,
                'lft' => 4,
                'rght' => 5
            ],
        [
            'id' => 4,
            'name' => 'Root-level metric 3',
            'lft' => 7,
            'rght' => 8
        ],
        [
            'id' => 5,
            'name' => 'Identical name',
            'lft' => 9,
            'rght' => 10
        ],

        // District
        [
            'id' => 6,
            'context' => 'district',
            'name' => 'Root-level metric 1',
            'lft' => 11,
            'rght' => 16
        ],
            [
                'id' => 7,
                'context' => 'district',
                'name' => 'Child metric',
                'parent_id' => 1,
                'lft' => 12,
                'rght' => 13
            ],
            [
                'id' => 8,
                'context' => 'district',
                'name' => 'Identical name',
                'parent_id' => 1,
                'lft' => 14,
                'rght' => 15
            ],
        [
            'id' => 9,
            'context' => 'district',
            'name' => 'Root-level metric 3',
            'lft' => 17,
            'rght' => 18
        ],
        [
            'id' => 10,
            'context' => 'district',
            'name' => 'Identical name',
            'lft' => 19,
            'rght' => 20
        ],

        // Hidden
        [
            'id' => self::HIDDEN_SCHOOL_METRIC,
            'visible' => 0,
            'name' => 'Hidden school metric',
            'lft' => 19,
            'rght' => 20
        ],
        [
            'id' => self::HIDDEN_DISTRICT_METRIC,
            'context' => 'district',
            'visible' => 0,
            'name' => 'Hidden district metric',
            'lft' => 21,
            'rght' => 22
        ],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Initialization function
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        foreach ($this->records as &$record) {
            $record += $this->defaultData;
        }
    }
}
