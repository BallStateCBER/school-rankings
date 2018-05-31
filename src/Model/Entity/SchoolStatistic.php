<?php
namespace App\Model\Entity;

/**
 * SchoolStatistic Entity
 *
 * @property int $id
 * @property int $metric_id
 * @property int $school_id
 * @property string $value
 * @property int $year
 * @property bool $contiguous
 * @property string $file
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Metric $metric
 * @property \App\Model\Entity\School $school
 */
class SchoolStatistic extends Statistic
{

}
