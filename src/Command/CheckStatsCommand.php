<?php
namespace App\Command;

use App\Model\Entity\Statistic;
use App\Model\Table\MetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Validation\Validator;

/**
 * Class CheckStatisticsCommand
 * @package App\Command
 * @property array $blankValues
 * @property ConsoleIo $io
 * @property int $pageCount
 * @property int $statsCount
 * @property int $statsPageSize
 * @property MetricsTable $metricsTable
 * @property StatisticsTable $statsTable
 * @property Validator $validator
 */
class CheckStatsCommand extends Command
{
    private $blankValues;
    private $io;
    private $metricsTable;
    private $pageCount;
    private $statsCount;
    private $statsPageSize = 100;
    private $statsTable;
    private $validator;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');
        $this->statsCount = $this->statsTable->find()->count();
        $this->pageCount = ceil($this->statsCount / $this->statsPageSize);
        $this->validator = $this->statsTable->getValidator('default');
    }

    /**
     * Processes location info file and updates the database
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;
        $this->checkForRuleValidations();
    }

    /**
     * Creates a progress bar, draws it, and returns it
     *
     * @param int $total Total number of items to be processed
     * @return ProgressHelper
     */
    private function makeProgressBar($total)
    {
        /** @var ProgressHelper $progress */
        $progress = $this->io->helper('Progress');
        $progress->init([
            'total' => $total,
            'width' => 60,
        ]);
        $progress->draw();

        return $progress;
    }

    /**
     * Returns a page of statistics
     *
     * @param int $page Page number
     * @return \Cake\Datasource\ResultSetInterface|Statistic[]
     */
    private function getPaginatedStats($page)
    {
        return $this->statsTable->find()
            ->limit($this->statsPageSize)
            ->page($page)
            ->all();
    }

    /**
     * Displays a string in the format "Took X hours, X minutes, etc.
     *
     * @param int $start Timestamp of beginning of process
     * @return void
     */
    private function displayTimeElapsed(int $start)
    {
        $duration = Time::createFromTimestamp($start)->timeAgoInWords();
        $this->io->out(sprintf(
            'Took %s',
            str_replace(' ago', '', $duration)
        ));
    }

    /**
     * Displays a message and a prompt for a 'y' or 'n' response and returns TRUE if response is 'y'
     *
     * @param string $msg Message to display
     * @param string $default Default selection (leave blank for 'y')
     * @return bool
     */
    private function getConfirmation($msg, $default = 'y')
    {
        return $this->io->askChoice(
            $msg,
            ['y', 'n'],
            $default
        ) == 'y';
    }

    /**
     * Iterates through statistics and checks for validation or application rule violation
     *
     * @return void
     * @throws \Aura\Intl\Exception
     */
    private function checkForRuleValidations()
    {
        if (!$this->getConfirmation('Check for application rule / validation errors?')) {
            return;
        }

        $pauseOnError = $this->getConfirmation('Pause on error?');

        $start = time();
        $progress = $this->makeProgressBar($this->pageCount);
        $allErrors = [];
        for ($page = 1; $page <= $this->pageCount; $page++) {
            $stats = $this->getPaginatedStats($page);
            foreach ($stats as $stat) {
                $errors = $this->statsTable
                    ->getValidator('default')
                    ->errors($stat->toArray());
                $ruleViolation = !$this->statsTable->checkRules($stat, 'create');

                if ($errors || $ruleViolation) {
                    $allErrors[$stat->id] = [
                        'errors' => $errors,
                        'ruleViolation' => $ruleViolation
                    ];
                    if ($pauseOnError) {
                        $this->io->error('Error with stat #' . $stat->id);
                        print_r($stat->getErrors());
                        $this->io->out('Stat values:');
                        print_r($stat->toArray());
                        $this->io->ask('Press enter to continue');
                    }
                }
                unset($stat);
            }
            unset($stats);

            $progress->increment(1)->draw();
        }

        $this->displayTimeElapsed($start);
        $this->io->out();
        if ($allErrors) {
            $this->io->error(sprintf(
                '%s %s with errors found',
                number_format(count($allErrors)),
                __n('stat', 'stat', count($allErrors))
            ));
            foreach ($allErrors as $statId => $info) {
                $this->io->out('Stat #' . $statId);
                if ($info['ruleViolation']) {
                    $this->io->out('Application rules failed');
                }
                if ($info['errors']) {
                    $this->io->out();
                }
                if (!$this->getConfirmation('Continue?')) {
                    break;
                }
            }
        }
    }

    /**
     * A hack to force validation to be run on the entity
     *
     * @param Statistic $stat Statistic entity
     * @return \Cake\Datasource\EntityInterface|Statistic
     */
    private function forceValidation(Statistic &$stat)
    {
        $originalValues = $stat->toArray();

        if (!$this->blankValues) {
            $fieldCount = count($originalValues);
            $this->blankValues = array_combine(
                array_keys($originalValues),
                array_fill(0, $fieldCount, null)
            );
        }

        $stat = $this->statsTable->patchEntity($stat, $this->blankValues, ['validate' => false]);

        return $this->statsTable->patchEntity($stat, $originalValues);
    }
}
