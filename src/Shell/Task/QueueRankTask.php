<?php
namespace App\Shell\Task;

use Exception;
use Queue\Shell\Task\QueueTask;

/**
 * Class QueueRankTask
 * @package App\Shell\Task
 * @property RankTask $Rank
 */
class QueueRankTask extends QueueTask
{
    public $tasks = ['Rank'];

    /**
     * Timeout for this task in seconds, after which the task is reassigned to a new worker.
     *
     * @var int
     */
    public $timeout = (60 * 10);

    /**
     * Number of times a failed instance of this task should be restarted before giving up.
     *
     * @var int
     */
    public $retries = 1;

    /**
     * Runs this task
     *
     * @param array $data The array passed to QueuedJobsTable::createJob()
     * @param int $jobId The id of the QueuedJob entity
     * @return void
     * @throws Exception
     */
    public function run(array $data, $jobId)
    {
        $rankingId = $data['rankingId'];

        $rankTask = new RankTask();
        $rankTask->initialize();
        $rankTask->setJobId($jobId);

        $rankTask->process($rankingId);
    }

    /**
     * Outputs a message explaining that this task cannot be added via CLI
     *
     * @return void
     */
    public function add()
    {
        $this->err('Task cannot be added via console');
    }
}
