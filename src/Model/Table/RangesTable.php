<?php
namespace App\Model\Table;

use App\Model\Entity\Range;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Ranges Model
 *
 * @property RankingsTable|BelongsToMany $Rankings
 *
 * @method Range get($primaryKey, $options = [])
 * @method Range newEntity($data = null, array $options = [])
 * @method Range[] newEntities(array $data, array $options = [])
 * @method Range|bool save(EntityInterface $entity, $options = [])
 * @method Range patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method Range[] patchEntities($entities, array $data, array $options = [])
 * @method Range findOrCreate($search, callable $callback = null, $options = [])
 */
class RangesTable extends Table
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

        $this->setTable('ranges');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsToMany('Rankings', [
            'foreignKey' => 'range_id',
            'targetForeignKey' => 'ranking_id',
            'joinTable' => 'rankings_ranges',
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
            ->scalar('center')
            ->maxLength('center', 255)
            ->requirePresence('center', 'create')
            ->notEmpty('center');

        $validator
            ->integer('distance')
            ->requirePresence('distance', 'create')
            ->notEmpty('distance');

        return $validator;
    }
}
