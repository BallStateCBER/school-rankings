<?php
namespace App\Model\Table;

use App\Model\Entity\State;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * States Model
 *
 * @property CitiesTable|HasMany $Cities
 * @property CountiesTable|HasMany $Counties
 * @property RankingsTable|BelongsToMany $Rankings
 * @property SchoolDistrictsTable|BelongsToMany $SchoolDistricts
 * @property SchoolsTable|BelongsToMany $Schools
 *
 * @method State get($primaryKey, $options = [])
 * @method State newEntity($data = null, array $options = [])
 * @method State[] newEntities(array $data, array $options = [])
 * @method State|bool save(EntityInterface $entity, $options = [])
 * @method State patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method State[] patchEntities($entities, array $data, array $options = [])
 * @method State findOrCreate($search, callable $callback = null, $options = [])
 */
class StatesTable extends Table
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

        $this->setTable('states');
        $this->setDisplayField('name');

        $this->hasMany('Cities', [
            'foreignKey' => 'state_id',
        ]);
        $this->hasMany('Counties', [
            'foreignKey' => 'state_id',
        ]);
        $this->belongsToMany('Rankings', [
            'foreignKey' => 'state_id',
            'targetForeignKey' => 'ranking_id',
            'joinTable' => 'rankings_states',
        ]);
        $this->belongsToMany('SchoolDistricts', [
            'foreignKey' => 'state_id',
            'targetForeignKey' => 'school_district_id',
            'joinTable' => 'school_districts_states',
        ]);
        $this->belongsToMany('Schools', [
            'foreignKey' => 'state_id',
            'targetForeignKey' => 'school_id',
            'joinTable' => 'schools_states',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param Validator $validator Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        $validator
            ->scalar('abbreviation')
            ->maxLength('abbreviation', 255)
            ->requirePresence('abbreviation', 'create')
            ->notEmpty('abbreviation');

        return $validator;
    }

    /**
     * Returns an array of state abbreviations, keyed with full state names
     *
     * @return array
     */
    public static function getAbbreviations()
    {
        return [
            'Indiana' => 'IN',
            'Illinois' => 'IL',
            'Ohio' => 'OH',
        ];
    }

    /**
     * Takes a state name/abbreviation and returns an abbreviation
     *
     * Used to take unknown state-identifying strings and normalize them to abbreviations
     *
     * @param string $name Full or abbreviated state name
     * @return string
     * @throws InternalErrorException
     */
    public static function abbreviateName($name)
    {
        $abbreviations = self::getAbbreviations();
        if (in_array($name, array_values($abbreviations))) {
            return $name;
        }

        if (array_key_exists($name, $abbreviations)) {
            return $abbreviations[$name];
        }

        throw new InternalErrorException('Unsupported state: ' . $name);
    }

    /**
     * Takes a state name/abbreviation and returns a full state name
     *
     * Used to take unknown state-identifying strings and normalize them to full names
     *
     * @param string $name Full or abbreviated state name
     * @return string
     * @throws InternalErrorException
     */
    public static function unabbreviateName($name)
    {
        $abbreviations = self::getAbbreviations();
        if (array_search($name, $abbreviations) !== false) {
            return array_search($name, $abbreviations);
        }

        if (array_key_exists($name, $abbreviations)) {
            return $name;
        }

        throw new InternalErrorException('Unsupported state: ' . $name);
    }
}
