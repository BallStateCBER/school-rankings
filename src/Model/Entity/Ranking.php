<?php
namespace App\Model\Entity;

use App\Model\Context\Context;
use App\Model\Table\MetricsTable;
use App\Model\Table\SchoolTypesTable;
use Cake\I18n\FrozenTime;
use Cake\I18n\Time;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\Utility\Hash;

/**
 * Ranking Entity
 *
 * @property int $id
 * @property int $user_id
 * @property int $formula_id
 * @property bool $for_school_districts
 * @property string $hash
 * @property FrozenTime $created
 *
 * Virtual properties
 * @property array $results Virtual property used to access results_schools or results_districts
 * @property string $url Virtual property for the full URL to view this set of ranking results
 * @property array $input_summary Virtual property for a summary of the inputs that generated this ranking
 * @property string $form_url Virtual property for the URL of an auto-populated formula form
 * @property string $formatted_date Virtual property for the formatted date the ranking was created
 *
 * @property User $user
 * @property Formula $formula
 * @property SchoolType $school_type
 * @property Grade[] $grades
 * @property City[] $cities
 * @property County[] $counties
 * @property Range[] $ranges
 * @property SchoolDistrict[] $school_districts
 * @property SchoolType[] $school_types
 * @property State[] $states
 * @property RankingResultsSchool[] $results_schools
 * @property RankingResultsSchoolDistrict[] $results_districts
 */
class Ranking extends Entity
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
        'user_id' => true,
        'formula_id' => true,
        'for_school_districts' => true,
        'hash' => true,
        'created' => true,
        'user' => true,
        'formula' => true,
        'school_type' => true,
        'grades' => true,
        'cities' => true,
        'counties' => true,
        'ranges' => true,
        'school_districts' => true,
        'school_types' => true,
        'states' => true,
        'results_districts' => true,
        'results_schools' => true,
    ];

    /**
     * Adds the "path" property to each metric
     *
     * "Path" is an array of Metric entities that start with the topmost parent metric and end with the final metric
     * that the user selected. This is returned via the /api/rankings/get API so that JsTree parent node groups can be
     * opened before their children are selected
     *
     * @return void
     * @throws \Exception
     */
    public function addMetricPaths()
    {
        /** @var \App\Model\Table\MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $context = $this->for_school_districts ? Context::DISTRICT_CONTEXT : Context::SCHOOL_CONTEXT;
        $metricsTable->setScope($context);
        foreach ($this->formula->criteria as &$criterion) {
            $path = $metricsTable
                ->find('path', ['for' => $criterion->metric->id])
                ->select(['id']);
            $criterion->metric->path = $path;
        }
    }

    /**
     * Separates out and sorts results that have no statistical data
     *
     * @return \App\Model\Entity\RankingResultsSchool[]|\App\Model\Entity\RankingResultsSchoolDistrict[]
     */
    public function getResultsWithoutData()
    {
        $resultsWithoutData = [];
        foreach ($this->results as $i => $result) {
            if ($result->data_completeness !== 'empty') {
                continue;
            }

            /* The array's keys need to be each subject's name so that the array can be sorted, but each key also needs
             * to be unique so that two identically-named subjects don't result in one member of the array overwriting
             * the other. To solve this problem, the subject's ID is appended to the end of their name. */
            $subject = isset($result->school) ? 'school' : 'school_district';
            $key = $result->$subject->name . $result->$subject->id;
            $resultsWithoutData[$key] = $result;
        }
        ksort($resultsWithoutData);

        return array_values($resultsWithoutData);
    }

    /**
     * Separates out and returns results that have non-empty statistical data
     *
     * @return \App\Model\Entity\RankingResultsSchool[]|\App\Model\Entity\RankingResultsSchoolDistrict[]
     */
    public function getResultsWithData()
    {
        $resultsWithData = [];
        foreach ($this->results as $i => $result) {
            if ($result->data_completeness != 'empty') {
                $resultsWithData[] = $result;
            }
        }

        return $resultsWithData;
    }

    /**
     * Returns results (ignoring those without statistical data), grouped into ranks
     *
     * @return array
     */
    public function getRankedResultsWithData()
    {
        $resultsWithData = $this->getResultsWithData();

        // Group results by rank
        $groupedResults = [];
        foreach ($resultsWithData as $result) {
            $groupedResults[$result->rank][] = $result;
        }

        // Alphabetize results in each rank
        foreach ($groupedResults as $rank => $resultsInRank) {
            $sortedResults = [];
            foreach ($resultsInRank as $resultInRank) {
                $subject = isset($resultInRank->school) ? 'school' : 'school_district';
                // Combine name and ID in case any two subjects (somehow) have identical names
                $key = $resultInRank->$subject->name . $resultInRank->$subject->id;
                $sortedResults[$key] = $resultInRank;
            }
            ksort($sortedResults);
            $groupedResults[$rank] = array_values($sortedResults);
        }

        // Convert into numerically-indexed array so it can be passed to a React component
        $indexedResults = [];
        foreach ($groupedResults as $rank => $resultsInRank) {
            $indexedResults[] = [
                'rank' => $rank,
                'subjects' => $resultsInRank,
            ];
        }

        return $indexedResults;
    }

    /**
     * Virtual field that returns an array describing the form inputs that resulted in this ranking record being created
     *
     * Used to automatically populate the ranking formula form
     *
     * @return array
     */
    protected function _getInputSummary()
    {
        $schoolTypeIds = Hash::extract($this->school_types, '{n}.id');

        return [
            'context' => $this->for_school_districts ? Context::DISTRICT_CONTEXT : Context::SCHOOL_CONTEXT,
            'counties' => array_map(function (County $county) {
                return [
                    'id' => $county->id,
                    'name' => $county->name,
                ];
            }, $this->counties),
            'criteria' => $this->formula->criteria,
            'gradeIds' => Hash::extract($this->grades, '{n}.id'),
            'onlyPublic' => $schoolTypeIds === [SchoolTypesTable::SCHOOL_TYPE_PUBLIC],
            'schoolTypeIds' => $schoolTypeIds,
        ];
    }

    /**
     * A virtual property that allows 'results' to return whichever is populated between *_schools and *_districts
     *
     * @return array
     */
    protected function _getResults()
    {
        if ($this->_properties['results_schools']) {
            return $this->_properties['results_schools'];
        }

        if ($this->_properties['results_districts']) {
            return $this->_properties['results_districts'];
        }

        return [];
    }

    /**
     * Ensures that all statistics for percentage-style metrics are formatted correctly
     *
     * This makes up for the fact that Indiana Department of Education data formats some percentage stats as
     * floats (e.g. 0.41) and some as strings (e.g. "41%")
     *
     * @return void
     */
    public function formatNumericValues()
    {
        $metricIsPercent = [];
        $resultsFields = ['results_districts', 'results_schools'];
        /** @var MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        foreach ($resultsFields as $results) {
            foreach ($this->$results as &$subject) {
                foreach ($subject['statistics'] as &$statistic) {
                    // Should this statistic be formatted as a percentage?
                    $metricId = $statistic['metric_id'];
                    if (!isset($metricIsPercent[$metricId])) {
                        $metricIsPercent[$metricId] = $metricsTable->isPercentMetric($metricId);
                    }

                    // Percent values
                    if ($metricIsPercent[$metricId]) {
                        if (!Statistic::isPercentValue($statistic['value'])) {
                            $statistic['value'] = Statistic::convertValueToPercent($statistic['value']);
                        }
                        continue;
                    }

                    // Non-percent numeric values
                    if (is_numeric($statistic['value'])) {
                        $statistic['value'] = number_format($statistic['value']);
                    }
                }
            }
        }
    }

    /**
     * Loop through all statistics in these results and mark those statistics that have the highest values in this set
     *
     * @return void
     */
    public function rankStatistics()
    {
        // Collect all statistic values
        $statisticValues = [];
        $resultsKey = $this->results_districts ? 'results_districts' : 'results_schools';
        foreach ($this->$resultsKey as &$subject) {
            /** @var Statistic $statistic */
            foreach ($subject->statistics as &$statistic) {
                $value = $statistic->numeric_value;
                $metricId = $statistic->metric_id;
                $statisticValues[$metricId][] = $value;
            }
        }

        // Ensure that all values are unique
        foreach ($statisticValues as $metricId => &$values) {
            $values = array_unique($values);
        }

        // Order each set of statistics by value
        foreach ($statisticValues as $metricId => &$values) {
            rsort($values);
        }

        // Keep track of which statistics are in 1st, 2nd, and 3rd place in their metrics in order to discover ties
        $rankedStatistics = [];

        // Flag each statistic if it's the 1st, 2nd, or 3rd highest value (including tied ranks)
        foreach ($this->$resultsKey as &$subject) {
            /** @var Statistic $statistic */
            foreach ($subject->statistics as &$statistic) {
                $metricId = $statistic->metric_id;
                $value = $statistic->numeric_value;
                $statistic->rank = null;
                $statistic->rankTied = false;
                for ($n = 1; $n <= 3; $n++) {
                    $nthRankedValue = $statisticValues[$metricId][$n - 1];
                    if ($value == $nthRankedValue) {
                        $statistic->rank = $n;
                        $rankedStatistics[$metricId][$n][] = $statistic->id;
                        break;
                    }
                }
            }
        }

        // Note any ties
        foreach ($this->$resultsKey as &$subject) {
            /** @var Statistic $statistic */
            foreach ($subject->statistics as &$statistic) {
                $metricId = $statistic->metric_id;
                if (!isset($rankedStatistics[$metricId][$statistic->rank])) {
                    continue;
                }

                $statistic->rankTied = count($rankedStatistics[$metricId][$statistic->rank]) > 1;
            }
        }
    }

    /**
     * A virtual field that generates the full URL to view a set of ranking results
     *
     * @return string
     */
    protected function _getUrl()
    {
        return Router::url(
            [
                'plugin' => false,
                'prefix' => false,
                'controller' => 'Rankings',
                'action' => 'view',
                'hash' => $this->hash,
            ],
            true
        );
    }

    /**
     * A virtual field to generate a full URL to a formula form auto-populated with the inputs to produce this ranking
     *
     * @return string
     */
    protected function _getFormUrl()
    {
        return Router::url(
            [
                'plugin' => false,
                'prefix' => false,
                'controller' => 'Formulas',
                'action' => 'form',
                '?' => ['r' => $this->hash],
            ],
            true
        );
    }

    /**
     * A virtual field that returns the formatted and timezone-corrected date that this ranking record was created
     *
     * @return string
     */
    protected function _getFormattedDate()
    {
        $Time = new Time($this->created);

        return $Time->i18nFormat('MMMM d, Y', 'America/New_York');
    }
}
