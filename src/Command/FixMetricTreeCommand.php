<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Table\MetricsTable;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;

/**
 * Class FixMetricTreeCommand
 * @package App\Command
 */
class FixMetricTreeCommand extends CommonCommand
{

    /**
     * Fixes tree structure errors
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        parent::execute($args, $io);
        /** @var MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        foreach (Context::getContexts() as $context) {
            $start = time();
            $io->out("Recovering $context metric tree...");
            $metricsTable->setScope($context);
            $metricsTable->recover();
            $this->io->overwrite(sprintf(
                ' - Done %s',
                $this->getDuration($start)
            ));
        }
    }
}
