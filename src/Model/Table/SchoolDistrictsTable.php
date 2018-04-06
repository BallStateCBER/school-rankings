<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolDistrict;
use App\Shell\ImportShell;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolDistricts Model
 *
 * @property SchoolDistrictStatisticsTable|HasMany $SchoolDistrictStatistics
 * @property SchoolsTable|HasMany $Schools
 * @property RankingsTable|BelongsToMany $Rankings
 * @property CitiesTable|BelongsToMany $Cities
 * @property CountiesTable|BelongsToMany $Counties
 * @property StatesTable|BelongsToMany $States
 *
 * @method SchoolDistrict get($primaryKey, $options = [])
 * @method SchoolDistrict newEntity($data = null, array $options = [])
 * @method SchoolDistrict[] newEntities(array $data, array $options = [])
 * @method SchoolDistrict|bool save(EntityInterface $entity, $options = [])
 * @method SchoolDistrict patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolDistrict[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolDistrict findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class SchoolDistrictsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('school_districts');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('SchoolDistrictStatistics', [
            'foreignKey' => 'school_district_id'
        ]);
        $this->hasMany('Schools', [
            'foreignKey' => 'school_district_id'
        ]);
        $this->belongsToMany('Rankings', [
            'foreignKey' => 'school_district_id',
            'targetForeignKey' => 'ranking_id',
            'joinTable' => 'rankings_school_districts'
        ]);
        $this->belongsToMany('Cities', [
            'foreignKey' => 'school_district_id',
            'targetForeignKey' => 'city_id',
            'joinTable' => 'school_districts_cities'
        ]);
        $this->belongsToMany('Counties', [
            'foreignKey' => 'school_district_id',
            'targetForeignKey' => 'county_id',
            'joinTable' => 'school_districts_counties'
        ]);
        $this->belongsToMany('States', [
            'foreignKey' => 'school_district_id',
            'targetForeignKey' => 'state_id',
            'joinTable' => 'school_districts_states'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        $validator
            ->scalar('url')
            ->maxLength('url', 255)
            ->allowEmpty('url');

        $validator
            ->scalar('code')
            ->maxLength('code', 255)
            ->requirePresence('code', 'create')
            ->notEmpty('code');

        return $validator;
    }

    /**
     * Finds a school district with a matching code or creates a new record and returns a record ID
     *
     * @param string $code School district code
     * @param string $name School name
     * @param ImportShell|null $shell ImportShell object
     * @return int
     */
    public function getOrCreate($code, $name, $shell = null)
    {
        $record = $this->find()
            ->select(['id'])
            ->where(['code' => $code])
            ->first();

        if ($record) {
            return $record->id;
        }

        $record = $this->newEntity(compact('code', 'name'));
        $this->saveOrFail($record);

        if ($shell) {
            $msg = " - Added district #$code: $name";
            $shell->out($msg);
        }

        return $record->id;
    }
}
