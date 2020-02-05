<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolType;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolTypes Model
 *
 * @property RankingsTable|HasMany $Rankings
 * @property SchoolsTable|HasMany $Schools
 *
 * @method SchoolType get($primaryKey, $options = [])
 * @method SchoolType newEntity($data = null, array $options = [])
 * @method SchoolType[] newEntities(array $data, array $options = [])
 * @method SchoolType|bool save(EntityInterface $entity, $options = [])
 * @method SchoolType patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolType[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolType findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class SchoolTypesTable extends Table
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

        $this->setTable('school_types');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Rankings', [
            'foreignKey' => 'school_type_id',
        ]);
        $this->hasMany('Schools', [
            'foreignKey' => 'school_type_id',
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

        return $validator;
    }

    /**
     * Returns an array of names of school types
     *
     * @return array
     */
    public static function getNames()
    {
        return [
            'public',
            'private',
            'charter',
        ];
    }

    /**
     * Returns an array of grade entities, keyed by their names, creating them if necessary
     *
     * @return array
     */
    public function getAll()
    {
        $typeNames = self::getNames();
        $types = [];
        foreach ($typeNames as $typeName) {
            $conditions = ['name' => $typeName];
            /** @var SchoolType $type */
            $type = $this->find()
                ->where($conditions)
                ->first();
            if ($type) {
                $types[$type->name] = $type;
                continue;
            }

            $type = $this->newEntity($conditions);
            if ($this->save($type)) {
                $types[$type->name] = $type;
                continue;
            }

            throw new InternalErrorException('Error saving school type ' . $typeName);
        }

        return $types;
    }
}
