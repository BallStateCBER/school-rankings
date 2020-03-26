<?php
namespace App\Model\Table;

use App\Model\Entity\ImportedFile;
use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ImportedFiles Model
 *
 * @method ImportedFile get($primaryKey, $options = [])
 * @method ImportedFile newEntity($data = null, array $options = [])
 * @method ImportedFile[] newEntities(array $data, array $options = [])
 * @method ImportedFile|bool save(EntityInterface $entity, $options = [])
 * @method ImportedFile patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method ImportedFile[] patchEntities($entities, array $data, array $options = [])
 * @method ImportedFile findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
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
     * @param Validator $validator Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('year')
            ->requirePresence('year', 'create');

        $validator
            ->scalar('file')
            ->maxLength('file', 255)
            ->requirePresence('file', 'create')
            ->notEmptyString('file');

        return $validator;
    }

    /**
     * Returns the date that the specified file was last imported, or null if it hasn't been imported yet
     *
     * @param int|string $year Four-digit year
     * @param string $file File path, starting with $year . DS
     * @return FrozenTime|null
     */
    public function getImportDate($year, $file)
    {
        /** @var ImportedFile $result */
        $result = $this->find()
            ->select(['created'])
            ->where([
                'year' => (int)$year,
                'file' => $file,
            ])
            ->orderDesc('created')
            ->first();

        return $result ? $result->created : null;
    }
}
