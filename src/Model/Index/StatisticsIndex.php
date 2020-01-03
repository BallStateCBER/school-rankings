<?php
namespace App\Model\Index;

use Cake\Core\Configure;
use Cake\ElasticSearch\Index;

class StatisticsIndex extends Index
{
    /**
     * The name of index in Elasticsearch
     *
     * @return  string
     */
    public function getName()
    {
        return Configure::read('Elasticsearch.statisticsIndex');
    }

    /**
     * The name of mapping type in Elasticsearch
     *
     * @return  string
     */
    public function getType()
    {
        return '_doc';
    }
}
