<?php
namespace App\Model\Table;

use App\Model\Entity\Grade;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Grades Model
 *
 * @property RankingsTable|HasMany $Rankings
 * @property SchoolsTable|BelongsToMany $Schools
 *
 * @method Grade get($primaryKey, $options = [])
 * @method Grade newEntity($data = null, array $options = [])
 * @method Grade[] newEntities(array $data, array $options = [])
 * @method Grade|bool save(EntityInterface $entity, $options = [])
 * @method Grade patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method Grade[] patchEntities($entities, array $data, array $options = [])
 * @method Grade findOrCreate($search, callable $callback = null, $options = [])
 */
class GradesTable extends Table
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

        $this->setTable('grades');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsToMany('Rankings', [
            'foreignKey' => 'grade_id',
            'targetForeignKey' => 'school_id',
            'joinTable' => 'rankings_grades'
        ]);
        $this->belongsToMany('Schools', [
            'foreignKey' => 'grade_id',
            'targetForeignKey' => 'school_id',
            'joinTable' => 'schools_grades'
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

        return $validator;
    }

    /**
     * Returns a hard-coded array of grade names, keyed by the abbreviations used by the Indiana Department of Education
     *
     * @return array
     * @throws InternalErrorException
     */
    public static function getGradeNames()
    {
        $grades = [
            'PW' => 'Pre-school (ages 0-2)',
            'PK' => 'Pre-kindergarten (ages 3-5)',
            'KG' => 'Kindergarten',
        ];

        for ($n = 1; $n <= 12; $n++) {
            $grades[$n] = 'Grade ' . $n;
        }

        return $grades;
    }

    /**
     * Returns an array of grade entities, keyed by their names, creating them if necessary
     *
     * @return array
     */
    public function getAll()
    {
        $gradeNames = self::getGradeNames();
        $grades = [];
        foreach ($gradeNames as $gradeAbbreviation => $gradeName) {
            $conditions = ['name' => $gradeName];
            /** @var Grade $grade */
            $grade = $this->find()
                ->where($conditions)
                ->first();
            if ($grade) {
                $grades[$grade->name] = $grade;
                continue;
            }

            $grade = $this->newEntity($conditions);
            if ($this->save($grade)) {
                $grades[$grade->name] = $grade;
                continue;
            }

            throw new InternalErrorException('Error saving grade ' . $gradeName);
        }

        return $grades;
    }

    /**
     * Returns an array of all Grade entities in the specified range
     *
     * @param string $lowGradeName Full name or abbreviation of lowest grade
     * @param string $highGradeName Full name or abbreviation of highest grade
     * @param Grade[]|null $allGrades An optional array of all Grade entities
     * @return array
     * @throws InternalErrorException
     */
    public function getGradesInRange($lowGradeName, $highGradeName, $allGrades = null)
    {
        $gradeNames = self::getGradeNames();
        if (!$allGrades) {
            $allGrades = $this->getAll();
        }

        // Convert from abbreviations to full names
        $lowGradeName = (string)$lowGradeName;
        if (!array_key_exists($lowGradeName, $allGrades)) {
            $lowGradeName = str_replace('0', '', $lowGradeName);
            if (array_key_exists($lowGradeName, $gradeNames)) {
                $lowGradeName = $gradeNames[$lowGradeName];
            } else {
                throw new InternalErrorException('Unrecognized grade: ' . $lowGradeName);
            }
        }
        $highGradeName = (string)$highGradeName;
        if (!array_key_exists($highGradeName, $allGrades)) {
            $highGradeName = str_replace('0', '', $highGradeName);
            if (array_key_exists($highGradeName, $gradeNames)) {
                $highGradeName = $gradeNames[$highGradeName];
            } else {
                throw new InternalErrorException('Unrecognized grade: ' . $highGradeName);
            }
        }

        $grades = [];
        foreach ($allGrades as $gradeName => $grade) {
            if ($gradeName == $lowGradeName) {
                $grades[] = $grade;
                continue;
            }
            if ($gradeName == $highGradeName) {
                $grades[] = $grade;

                return $grades;
            }

            if ($grades) {
                $grades[] = $grade;
            }
        }

        return $grades;
    }
}
