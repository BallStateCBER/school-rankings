<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Model\Entity\Criterion;
use App\Model\Entity\Formula;
use App\Model\Table\CriteriaTable;
use App\Model\Table\FormulasTable;
use Cake\Http\Exception\BadRequestException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

/**
 * @property CriteriaTable $criteriaTable
 * @property FormulasTable $formulasTable
 */
class FormulasController extends AppController
{
    private $criteriaTable;
    private $formulasTable;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        $this->criteriaTable = TableRegistry::getTableLocator()->get('Criteria');
        $this->formulasTable = TableRegistry::getTableLocator()->get('Formulas');
    }

    /**
     * Generates and returns a ranking according to provided formula parameters
     *
     * @return void
     * @throws \Exception
     * @throws BadRequestException
     */
    public function add()
    {
        $context = $this->request->getData('context');
        $criteria = $this->getCriteria();

        if ($criteria === false) {
            $success = false;
            $id = null;
        } else {
            $formula = $this->formulasTable->newEntity([
                'user_id' => null,
                'is_example' => false,
                'title' => '',
                'context' => $context,
                'hash' => FormulasTable::generateHash()
            ]);
            $formula->criteria = $criteria;
            $success = (bool)$this->formulasTable->save($formula);
            $id = $formula->id;

            if (!$success) {
                $this->logFormulaError($formula);
            }
        }

        $this->set([
            '_serialize' => ['success', 'id'],
            'success' => $success,
            'id' => $id
        ]);
    }

    /**
     * Returns an array of Criteria entities generated from request data or false on error
     *
     * @return array|bool
     */
    private function getCriteria()
    {
        $criteriaData = $this->request->getData('criteria');
        $criteria = [];
        $criterionError = false;
        foreach ($criteriaData as $criterionData) {
            /** @var Criterion $criterion */
            $criterion = $this->criteriaTable->newEntity([
                'metric_id' => $criterionData['metric']['metricId'],
                'weight' => 100,
                'preference' => 'high'
            ]);
            $errors = $criterion->getErrors();
            $passesRules = $this->formulasTable->checkRules($criterion, 'create');
            $criterionError = $errors || !$passesRules;
            if ($criterionError) {
                $this->logCriterionError($criterion);
                break;
            }
            $criteria[] = $criterion;
        }

        return $criterionError ? false : $criteria;
    }

    /**
     * Logs error in creating formula
     *
     * @param Formula $formula Formula entity
     * @return void
     */
    private function logFormulaError($formula)
    {
        $errors = $formula->getErrors();
        $passesRules = $this->formulasTable->checkRules($formula, 'create');
        if ($errors || !$passesRules) {
            $errorMsg = 'There was an error creating that formula.';
            if ($errors) {
                foreach (Hash::flatten($errors) as $field => $errorMsg) {
                    $errorMsg .= "\n - $errorMsg ($field)";
                }
            }
            if (!$passesRules) {
                $errorMsg .= "\n - Did not pass application rules";
            }
            Log::write('error', $errorMsg);
        }
    }

    /**
     * Logs error in creating criterion
     *
     * @param Criterion $criterion Criterion entity
     * @return void
     */
    private function logCriterionError($criterion)
    {
        $errors = $criterion->getErrors();
        $passesRules = $this->criteriaTable->checkRules($criterion, 'create');
        if ($errors || !$passesRules) {
            $errorMsg = 'There was an error recording that criterion.';
            if ($errors) {
                foreach (Hash::flatten($errors) as $field => $errorMsg) {
                    $errorMsg .= "\n - $errorMsg ($field)";
                }
            }
            if (!$passesRules) {
                $errorMsg .= "\n - Did not pass application rules";
            }
            Log::write('error', $errorMsg);
        }
    }
}
