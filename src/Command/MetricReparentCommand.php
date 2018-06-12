<?php
namespace App\Command;

use App\Model\Entity\Metric;
use App\Model\Table\MetricsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\TableRegistry;

/**
 * Class MetricReparentCommand
 * @package App\Command
 * @property bool $moveToRoot
 * @property int[] $childMetricIds
 * @property int|string $parentMetricId
 * @property Metric $parentMetric
 * @property Metric[] $childMetrics
 * @property MetricsTable $metricsTable
 * @property string $context
 */
class MetricReparentCommand extends Command
{
    private $childMetricIds;
    private $childMetrics;
    private $context;
    private $metricsTable;
    private $moveToRoot;
    private $parentMetric;
    private $parentMetricId;

    /**
     * Initializes the command
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * Display help for this console.
     *
     * @param ConsoleOptionParser $parser Console options parser object
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser->addArguments([
            'context' => [
                'help' => 'Either "school" or "district"',
                'choices' => ['school', 'district'],
                'required' => true
            ],
            'childMetrics' => [
                'help' => 'One or more metric IDs (comma separated) or ranges (with dashes); e.g. "1,3-5,7-10"',
                'required' => true
            ],
            'newParent' => [
                'help' => 'The ID of the new parent metric or "root"',
                'required' => true
            ],
        ]);

        return $parser;
    }

    /**
     * Reparents the specified metric(s)
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     * @throws \Aura\Intl\Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->context = $args->getArgument('context');
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        $this->getParent($args, $io);

        $this->getChildren($args, $io);

        if ($this->getConfirmation($io)) {
            $this->reparent($io);
        }
    }

    /**
     * Finds the specified parent or notes that child metrics will be reparented to root
     *
     * @param Arguments $args Command arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    private function getParent(Arguments $args, ConsoleIo $io)
    {
        $this->parentMetricId = $args->getArgument('newParent');
        $this->parentMetric = null;
        $this->moveToRoot = false;
        if ($this->parentMetricId == 'root') {
            $this->moveToRoot = true;
        } else {
            try {
                $this->parentMetric = $this->metricsTable->get($this->parentMetricId);
            } catch (RecordNotFoundException $e) {
                $io->error('Parent metric not found');
                $this->abort();
            }
        }
    }

    /**
     * Finds the specified child metric(s) in the database
     *
     * @param Arguments $args Command arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    private function getChildren(Arguments $args, ConsoleIo $io)
    {
        // Collect child metric info
        $childMetricsString = $args->getArgument('childMetrics');
        $this->childMetricIds = [];
        $this->childMetrics = [];
        foreach (explode(',', $childMetricsString) as $range) {
            $dashCount = substr_count($range, '-');
            if (!$dashCount) {
                $this->childMetricIds[] = $range;
                continue;
            }
            if ($dashCount == 1) {
                list($rangeStart, $rangeEnd) = explode('-', $range);
                $this->childMetricIds = array_merge($this->childMetricIds, range($rangeStart, $rangeEnd));
                continue;
            }
            $io->error('Invalid range: ' . $range);
            $this->abort();
        }
        foreach ($this->childMetricIds as $childMetricId) {
            if ($childMetricId == $this->parentMetricId) {
                $io->error('Cannot make metric #' . $childMetricId . ' its own parent');
                $this->abort();
            }
            try {
                $this->addChildMetric($childMetricId, $io);
            } catch (RecordNotFoundException $e) {
                $io->error('Child metric #' . $childMetricId . ' not found');
                $this->abort();
            }
        }
    }

    /**
     * Outputs details about the forthcoming operation and prompts for confirmation
     *
     * @param ConsoleIo $io Console IO object
     * @throws \Aura\Intl\Exception
     * @return bool
     */
    private function getConfirmation(ConsoleIo $io)
    {
        if (count($this->childMetricIds) == 1) {
            $io->out('Child metric #' . $this->childMetricIds[0] . ': ' . $this->childMetrics[0]->name);
        } else {
            $io->out('Child ' . __n('metric:', 'metrics:', count($this->childMetricIds)));
            foreach ($this->childMetrics as $childMetric) {
                $io->out(' - #' . $childMetric->id . ': ' . $childMetric->name);
            }
        }
        if ($this->moveToRoot) {
            $io->out('Moving to root');
        } else {
            $io->out('New parent metric #' . $this->parentMetricId . ': ' . $this->parentMetric->name);
        }

        $continue = $io->askChoice('Continue?', ['y', 'n'], 'n');

        return $continue == 'y';
    }

    /**
     * Adds a metric record to $this->childMetrics
     *
     * @param int $childMetricId ID of child metric record
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    private function addChildMetric($childMetricId, ConsoleIo $io)
    {
        $childMetric = $this->metricsTable->get($childMetricId);
        $this->metricsTable->patchEntity($childMetric, ['parent_id' => $this->parentMetricId]);
        $errors = $childMetric->getErrors();
        $passesRules = $this->metricsTable->checkRules($childMetric, 'update');

        if (empty($errors) && $passesRules) {
            $this->childMetrics[] = $childMetric;

            return;
        }

        $msg = "\nCannot reparent metric #$childMetricId";
        $msg .= $errors
            ? "\nDetails:\n" . print_r($errors, true)
            : ' No details available. (Check for application rule violation)';
        $io->error($msg);
        $this->abort();
    }

    /**
     * Updates all metrics in $this->childMetrics
     *
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    private function reparent(ConsoleIo $io)
    {
        foreach ($this->childMetrics as $childMetric) {
            $io->out('Updating metric #' . $childMetric->id);
            if ($this->metricsTable->save($childMetric)) {
                $io->success('Done');
            } else {
                $io->error('Error');
            }
        }

        $io->out('Reparenting complete');
    }
}
