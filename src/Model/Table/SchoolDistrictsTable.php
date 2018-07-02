<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolDistrict;
use Cake\Console\ConsoleIo;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolDistricts Model
 *
 * @property StatisticsTable|HasMany $Statistics
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

        $this->hasMany('Statistics', [
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
     * @param ConsoleIo|null $io Console IO object
     * @return int
     */
    public function getOrCreate($code, $name, $io = null)
    {
        /** @var SchoolDistrict $record */
        $record = $this->find()
            ->select(['id', 'name'])
            ->where(['code' => $code])
            ->first();

        if ($record) {
            if ($io) {
                $msg = " - Identified district #$code: $record->name";
                $io->out($msg);
            }

            return $record->id;
        }

        $record = $this->newEntity(compact('code', 'name'));
        $this->saveOrFail($record);

        if ($io) {
            $msg = " - Added district #$code: $name";
            $io->out($msg);
        }

        return $record->id;
    }

    /**
     * Returns an array of school district IDOE codes that should be ignored
     *
     * These districts are outliers, e.g. don't have an associated geographic location
     *
     * @return array
     */
    public static function getIgnoredDistrictCodes()
    {
        return [
            '8801' // Community-based Preschools
        ];
    }
}
