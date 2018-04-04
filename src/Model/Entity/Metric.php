<?php
namespace App\Model\Entity;

use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Metric parent entity
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $type
 * @property int $parent_id
 * @property int $lft
 * @property int $rght
 * @property bool $selectable
 * @property \Cake\I18n\FrozenTime $created
 */
class Metric extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true
    ];

    /**
     * Returns TRUE if the metric is found in the database
     *
     * @param string $context Either 'school' or 'district'
     * @param int $metricId SchoolMetric ID or SchoolDistrictMetric ID
     * @return bool
     * @throws InternalErrorException
     */
    public static function recordExists($context, $metricId)
    {
        $count = self::getTable($context)->find()
            ->where(['id' => $metricId])
            ->count();

        return $count > 0;
    }

    /**
     * Adds a metric record to the appropriate table
     *
     * @param string $context Either 'school' or 'district'
     * @param string $metricName The name of the new metric
     * @param string $type Either 'numeric' or 'boolean'
     * @return \Cake\Datasource\EntityInterface
     * @throws Exception
     */
    public static function addRecord($context, $metricName, $type = 'numeric')
    {
        $table = self::getTable($context);
        $metric = $table->newEntity([
            'name' => $metricName,
            'description' => '',
            'selectable' => true,
            'type' => $type
        ]);

        if ($table->save($metric)) {
            return $metric;
        }

        $msg = 'Cannot add metric ' . $metricName . "\nDetails: " . print_r($metric->getErrors(), true);
        throw new Exception($msg);

    }

    /**
     * Returns a SchoolMetricsTable or SchoolDistrictMetricsTable
     *
     * @param string $context Either 'school' or 'district'
     * @return \Cake\ORM\Table
     * @throws InternalErrorException
     */
    public static function getTable($context)
    {
        switch ($context) {
            case 'school':
                return TableRegistry::get('SchoolMetrics');
            case 'district':
                return TableRegistry::get('SchoolDistrictMetrics');
            default:
                throw new InternalErrorException('Metric context "' .  $context . '" not recognized');
        }
    }
}
