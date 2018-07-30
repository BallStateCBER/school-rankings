<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * Ranking Entity
 *
 * @property int $id
 * @property int $user_id
 * @property int $formula_id
 * @property int $school_type_id
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
        'school_type_id' => true,
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
}
