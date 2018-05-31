<?php
namespace App\Model\Entity;

/**
 * SchoolDistrictStatistic Entity
 *
 * @property int $id
 * @property int $metric_id
 * @property int $school_district_id
 * @property string $value
 * @property int $year
 * @property bool $contiguous
 * @property string $file
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Metric $metric
 * @property \App\Model\Entity\SchoolDistrict $school_district
 */
class SchoolDistrictStatistic extends Statistic
{

}
