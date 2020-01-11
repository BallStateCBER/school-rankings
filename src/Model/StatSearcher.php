<?php
namespace App\Model;

use App\Model\Entity\Statistic;
use App\Model\Index\StatisticsIndex;
use App\Model\Table\StatisticsTable;
use Cake\Http\Exception\InternalErrorException;

/**
 * Class StatSearcher
 * @package App\Model
 * @property StatisticsIndex|StatisticsTable $datasource
 */
class StatSearcher
{
    private $dataSource;

    /**
     * StatSearcher constructor
     *
     * @param StatisticsIndex|StatisticsTable $datasource Either an Elasticsearch index or a database table
     */
    public function __construct($datasource)
    {
        $this->dataSource = $datasource;
    }

    /**
     * Returns the most recent year for which stats are found, optionally limited to a single metric or set of subjects
     *
     * @param array $options Array of options, including metric_id and school_ids or school_district_ids
     * @return int|null
     * @throws InternalErrorException
     */
    public function getMostRecentYear($options = [])
    {
        /** @var Statistic $stat */
        $query = $this->datasource
            ->find()
            ->select(['year'])
            ->orderDesc('year');

        if (isset($options['metric_id'])) {
            $query->where(['metric_id' => $options['metric_id']]);
        }
        if (isset($options['school_ids'])) {
            $query->where(['school_id in' => $options['school_ids']]);
        }
        if (isset($options['school_district_ids'])) {
            $query->where(['school_district_id in' => $options['school_district_ids']]);
        }

        $stat = $query->first();

        return $stat ? $stat->year : null;
    }
}
