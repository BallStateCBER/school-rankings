<?php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * GradesFixture
 *
 */
class GradesFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => '', 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'slug' => ['type' => 'string', 'length' => 5, 'null' => false, 'default' => '', 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
        'idoe_abbreviation' => ['type' => 'string', 'length' => 2, 'null' => false, 'default' => '', 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null, 'fixed' => null],
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
            'name' => 'Preschool',
            'slug' => 'ps',
            'idoe_abbreviation' => 'PW', // for some weird reason
        ],
        [
            'id' => 2,
            'name' => 'Kindergarten',
            'slug' => 'k',
            'idoe_abbreviation' => 'KG',
        ],
    ];

    /**
     * Init method
     *
     * @return void
     */
    public function init()
    {
        for ($n = 1; $n <= 12; $n++) {
            $this->records[] = [
                'id' => count($this->records) + 1,
                'name' => 'Grade ' . $n,
                'slug' => 'g' . $n,
                'idoe_abbreviation' => str_pad($n, 2, '0', STR_PAD_LEFT),
            ];
        }
        parent::init();
    }
}
