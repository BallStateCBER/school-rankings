<?php
namespace App\Ranking;

use App\Model\Context\Context;
use App\Model\Entity\County;
use App\Model\Entity\Criterion;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use Cake\Database\Expression\QueryExpression;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use DebugKit\DebugTimer;

/**
 * Class Ranking
 * @package App\Ranking
 * @property County[] $locations
 * @property Criterion[] $criteria
 * @property School[]|SchoolDistrict[] $subjects
 * @property string $context
 */
class Ranking
{
    private $context;
    private $criteria;
    private $locations;
    private $subjects;

    /**
     * Ranking constructor
     *
     * @param array $params Array of parameters
     * @throws InternalErrorException
     * @throws \Exception
     */
    public function __construct($params)
    {
        $requiredParameters = [
            'context',
            'locations',
            'criteria'
        ];
        foreach ($requiredParameters as $requiredParameter) {
            if (!array_key_exists($requiredParameter, $params)) {
                throw new InternalErrorException("Required parameter key $requiredParameter missing");
            }
        }

        $this->context = $params['context'];
        $this->locations = $params['locations'];
        $this->criteria = $params['criteria'];
        $this->subjects = $this->getSubjects();
    }

    /**
     * Returns either the schools or districts that are associated with the specified locations
     *
     * @return School[]|SchoolDistrict[]
     * @throws \Exception
     */
    private function getSubjects()
    {
        DebugTimer::start('getSubjects');
        $subjectTable = Context::getTable($this->context);
        $subjects = [];
        $metricIds = Hash::extract($this->criteria, '{n}.metric_id');
        foreach ($this->locations as $location) {
            $locationTableName = $this->getLocationTableName($location);
            $results = $subjectTable->find()
                ->matching($locationTableName, function (Query $q) use ($locationTableName, $location) {
                    return $q->where(["$locationTableName.id" => $location->id]);
                });

            // Use school/district IDs as keys to avoid duplicates
            foreach ($results as $result) {
                $subjects[$result->id] = $result;
            }
        }
        DebugTimer::stop('getSubjects');
        $statsTable = TableRegistry::getTableLocator()->get('Statistics');
        foreach ($subjects as $subject) {
            DebugTimer::start('get stats for subject ' . $subject->id);
            $query = $statsTable->find()
                ->select(['metric_id', 'value', 'year'])
                ->where([
                    function (QueryExpression $exp) use ($metricIds) {
                        return $exp->in('Statistics.metric_id', $metricIds);
                    },
                    Context::getLocationField($this->context) => $subject->id
                ])
                ->limit(1)
                ->orderDesc('year');
            $subject->statistics = $query->all();
            DebugTimer::stop('get stats for subject ' . $subject->id);
        }

        return $subjects;
    }

    /**
     * Groups subjects into full data, partial data, and no data categories
     *
     * @return array
     */
    private function getGroupedSubjects()
    {
        $retval = [
            'fullData' => [],
            'partialData' => [],
            'noData' => []
        ];

        $metricCount = count($this->criteria);

        foreach ($this->subjects as $subject) {
            $subjectStatCount = count($subject->statistics);
            if ($subjectStatCount == $metricCount) {
                $retval['fullData'][] = $subject;
                continue;
            }

            if ($subjectStatCount > 0) {
                $retval['partialData'][] = $subject;
                continue;
            }

            $retval['noData'][] = $subject;
        }

        return $retval;
    }

    /**
     * Returns grouped array of subjects ordered by their rank, according to the current formula
     *
     * @return array
     */
    public function getRanking()
    {
        $groupedSubjects = $this->getGroupedSubjects();

        // TODO: Set school scores and order each group by score

        return $groupedSubjects;
    }

    /**
     * Gets e.g. the string 'Counties' from a County-type object
     *
     * @param County $location Any location-type entity
     * @return string
     */
    private function getLocationTableName($location)
    {
        $namespacedClassName = get_class($location);
        $pos = strrpos($namespacedClassName, '\\');
        $className = substr($namespacedClassName, $pos + 1);

        return Inflector::pluralize($className);
    }
}
