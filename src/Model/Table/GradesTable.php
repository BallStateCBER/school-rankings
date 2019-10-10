<?php
namespace App\Model\Table;

use App\Model\Entity\Grade;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Table;
use Cake\Utility\Hash;
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

        $validator
            ->scalar('name')
            ->maxLength('name', 5)
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
     * Returns an array of grade entities, keyed by their IDOE abbreviations
     *
     * @return array
     */
    public function getAll()
    {
        $results = $this->find()
            ->orderAsc('id')
            ->toArray();

        return Hash::combine($results, '{n}.idoe_abbreviation', '{n}');
    }

    /**
     * Takes an IDOE grade abbreviation and an array of grade entities and returns the matching grade
     *
     * @param string $gradeAbbrev IDOE grade abbreviation
     * @param Grade[] $allGrades Array of all grade entities
     * @return Grade
     * @throws InternalErrorException
     */
    public function getGradeByIdoeAbbreviation($gradeAbbrev, $allGrades)
    {
        foreach ($allGrades as $grade) {
            if (in_array($grade->idoe_abbreviation, [$gradeAbbrev, '0' . $gradeAbbrev])) {
                return $grade;
            }
        }

        throw new InternalErrorException('No grade found for abbreviation ' . $gradeAbbrev);
    }

    /**
     * Returns an array of all Grade entities in the specified range
     *
     * @param string[] $gradeAbbrevs Array with keys 'low' and 'high' and IDOE abbreviation of grades
     * @param Grade[] $allGrades Array of all grade entities
     * @return array
     * @throws InternalErrorException
     */
    public function getGradesInRange($gradeAbbrevs, $allGrades)
    {
        // Get grade IDs corresponding to the low and high grade abbreviations
        $gradeIds = [
            'low' => null,
            'high' => null
        ];
        foreach ($gradeAbbrevs as $key => $gradeAbbrev) {
            $grade = $this->getGradeByIdoeAbbreviation($gradeAbbrev, $allGrades);
            $gradeIds[$key] = $grade->id;
        }

        // Filter out any grades outside of this range of IDs
        return Hash::filter($allGrades, function ($grade) use ($gradeIds) {
            return $grade->id < $gradeIds['low'] && $grade->id > $gradeIds['high'];
        });
    }
}
