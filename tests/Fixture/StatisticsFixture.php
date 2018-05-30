<?php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * StatisticsFixture
 *
 */
class StatisticsFixture extends TestFixture
{
    protected $locationField;

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'metric_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        '{location_id}' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'value' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'year' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'contiguous' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '1', 'comment' => '', 'precision' => null],
        'file' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci'
        ],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'metric_id' => 1,
            '{location_id}' => 1,
            'value' => '100',
            'year' => 2018
        ]
    ];

    /**
     * Initialization function
     *
     * @return void
     */
    public function init()
    {
        $locationPlaceholder = '{location_id}';
        $this->fields[$this->locationField] = $this->fields[$locationPlaceholder];
        unset($this->fields[$locationPlaceholder]);

        $defaultData = [
            'metric_id' => 1,
            '{location_id}' => 1,
            'value' => '100',
            'year' => 2018,
            'contiguous' => 1,
            'file' => 'import_file.xlsx',
            'created' => '2018-04-05 20:53:25',
            'modified' => '2018-04-05 20:53:25'
        ];

        foreach ($this->records as &$record) {
            $record += $defaultData;
            $record[$this->locationField] = $record[$locationPlaceholder];
            unset($record[$locationPlaceholder]);
        }

        parent::init();
    }
}
