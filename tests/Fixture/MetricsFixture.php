<?php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * MetricsFixture
 *
 */
class MetricsFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'description' => ['type' => 'text', 'length' => null, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'type' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'parent_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'lft' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'rght' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'selectable' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '0', 'comment' => '', 'precision' => null],
        'visible' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '1', 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci'
        ],
    ];

    /**
     * Records
     *
     * @var array
     */
    public $records = [
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
        $defaultData = [
            'description' => '',
            'type' => 'numeric',
            'parent_id' => null,
            'selectable' => 1,
            'visible' => 1,
            'created' => '2018-04-03 16:18:09'
        ];

        foreach ($this->records as &$record) {
            $record += $defaultData;
        }
    }
}
