<?php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * CriteriaFixture
 *
 */
class CriteriaFixture extends TestFixture
{

    /**
     * Table name
     *
     * @var string
     */
    public $table = 'criteria';

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'formula_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'metric_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'weight' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'preference' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci'
        ],
    ];
    // @codingStandardsIgnoreEnd

    private $defaultData = [
        'id' => 1,
        'formula_id' => 1,
        'metric_id' => 1,
        'weight' => 1,
        'preference' => 'high',
    ];

    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
        ],
        [
            'id' => 2,
            'formula_id' => 2,
        ],
        [
            'id' => 3,
            'metric_id' => 2,
        ],
        [
            'id' => 4,
            'metric_id' => 2,
            'formula_id' => 3,
        ],
        [
            'id' => 5,
            'metric_id' => 3,
            'formula_id' => 3,
        ],
        [
            'id' => 6,
            'metric_id' => 2,
            'formula_id' => 2,
        ],
        [
            'id' => 7,
            'metric_id' => 2,
            'formula_id' => 4,
        ],
        [
            'id' => 8,
            'metric_id' => 3,
            'formula_id' => 4,
        ],
    ];

    /**
     * Initialization function
     *
     * @return void
     */
    public function init()
    {
        foreach ($this->records as &$record) {
            $record += $this->defaultData;
        }

        parent::init();
    }
}
