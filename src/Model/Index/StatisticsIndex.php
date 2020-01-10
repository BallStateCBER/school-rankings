<?php
namespace App\Model\Index;

use App\Model\Entity\Statistic;
use Cake\Core\Configure;
use Cake\ElasticSearch\Index;
use Cake\Http\Exception\InternalErrorException;

class StatisticsIndex extends Index
{
    /**
     * Initialization method
     *
     * @param array $config Configuration options passed to the constructor
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->_name = Configure::read('Elasticsearch.statisticsIndex');
        $this->_type = '_doc';
    }

    /**
     * Returns the most recent year for which statistics are found, optionally for a specified metric
     *
     * @param int|null $metricId Optional metric ID
     * @return int|null
     * @throws InternalErrorException
     */
    public function getMostRecentYear($metricId = null)
    {
        /** @var Statistic $stat */
        $query = $this->find()
            ->select(['year'])
            ->order(['year' => 'asc']);

        if ($metricId) {
            if (!is_int($metricId)) {
                throw new InternalErrorException('Metric ID ' . $metricId . ' is invalid.');
            }
            $query->where(['metric_id' => $metricId]);
        }

        $stat = $query->first();

        return $stat ? $stat->year : null;
    }
}
