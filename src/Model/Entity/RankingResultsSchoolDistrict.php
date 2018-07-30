<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * RankingResultsSchoolDistrict Entity
 *
 * @property int $id
 * @property int $ranking_id
 * @property int $school_district_id
 * @property int $rank
 * @property string $data_completeness
 *
 * @property \App\Model\Entity\Ranking $ranking
 * @property \App\Model\Entity\SchoolDistrict $school_district
 */
class RankingResultsSchoolDistrict extends Entity
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
        'ranking_id' => true,
        'school_district_id' => true,
        'rank' => true,
        'data_completeness' => true,
        'ranking' => true,
        'school_district' => true
    ];
}
