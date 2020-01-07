<?php
namespace App\Model\Index;

use Cake\Core\Configure;
use Cake\ElasticSearch\Index;

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
}
