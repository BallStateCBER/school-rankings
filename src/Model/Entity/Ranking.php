<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;

/**
 * Ranking Entity
 *
 * @property int $id
 * @property int $user_id
 * @property int $formula_id
 * @property bool $for_school_districts
 * @property array $results
 * @property string $hash
 * @property FrozenTime $created
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
 * @property RankingResultsSchool[] $ResultsSchools
 * @property RankingResultsSchoolDistrict[] $ResultsDistricts
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
        'results_schools' => true
    ];

    /**
     * Allows 'results' to be used to access whichever is populated between results_schools and results_school_districts
     *
     * @return array
     */
    protected function _getResults()
    {
        if ($this->_properties['results_schools']) {
            return $this->_properties['results_schools'];
        }

        if ($this->_properties['results_school_districts']) {
            return $this->_properties['results_school_districts'];
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
            foreach ($subject['statistics'] as &$statistic) {
                $metricId = $statistic['metric_id'];
                $value = $statistic['value'];
                $statisticValues[$metricId][] = $value;
            }
        }

        // Order each set of statistics by value
        foreach ($statisticValues as $metricId => &$values) {
            rsort($values);
        }

        // To discover ties, keep track of which metrics have their 1st, 2nd, and 3rd highest stat values marked
        $statRanks = [];

        // Flag each statistic if it's (tied for) the 1st, 2nd, or 3rd highest value
        foreach ($this->$resultsKey as &$subject) {
            foreach ($subject['statistics'] as &$statistic) {
                $metricId = $statistic['metric_id'];
                $value = $statistic['value'];
                $statistic['rank'] = null;
                $statistic['rankTied'] = false;
                for ($n = 1; $n <= 3; $n++) {
                    if ($value == $statisticValues[$metricId][$n - 1]) {
                        $statistic['rank'] = $n;
                        if (isset($statRanks[$metricId][$n])) {
                            $statistic['rankTied'] = true;
                        } else {
                            $statRanks[$metricId][$n] = true;
                        }
                        break;
                    }
                }
            }
        }
    }
}
