<?php
namespace App\Model\Table;

use App\Model\Entity\ImportedFile;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ImportedFiles Model
 *
 * @method \App\Model\Entity\ImportedFile get($primaryKey, $options = [])
 * @method \App\Model\Entity\ImportedFile newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\ImportedFile[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ImportedFile|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\ImportedFile patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\ImportedFile[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\ImportedFile findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class ImportedFilesTable extends Table
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

        $this->setTable('imported_files');

        $this->addBehavior('Timestamp');
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
            ->integer('year')
            ->requirePresence('year', 'create')
            ->notEmpty('year');

        $validator
            ->scalar('file')
            ->maxLength('file', 255)
            ->requirePresence('file', 'create')
            ->notEmpty('file');

        return $validator;
    }

    /**
     * Returns the date that the specified file was last imported, or null if it hasn't been imported yet
     *
     * @param int|string $year Four-digit year
     * @param string $file File path, starting with $year . DS
     * @return \Cake\I18n\FrozenTime|null
     */
    public function getImportDate($year, $file)
    {
        /** @var ImportedFile $result */
        $result = $this->find()
            ->select(['created'])
            ->where([
                'year' => (int)$year,
                'file' => $file
            ])
            ->orderDesc('created')
            ->first();

        return $result ? $result->created : null;
    }
}
