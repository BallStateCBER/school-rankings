<?php
namespace App\Command;

use App\Model\Entity\SchoolDistrict;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\ORM\Query;
use Cake\ORM\ResultSet;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Utility\Hash;

/**
 * Class FixDistrictAssociationsCommand
 * @package App\Command
 * @property array $metrics
 * @property ConsoleIo $io
 * @property ProgressHelper $progress
 * @property ResultSet|SchoolDistrict[] $districts
 * @property SchoolDistrictsTable $districtsTable
 * @property SchoolsTable $schoolsTable
 */
class FixDistrictAssociationsCommand extends Command
{
    private $districts;
    private $districtsTable;
    private $io;
    private $progress;
    private $schoolsTable;

    /**
     * Fixes statistic values like "0.025" that should be stored as "2.5%"
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->districtsTable = TableRegistry::getTableLocator()->get('SchoolDistricts');
        $this->io = $io;
        $this->progress = $this->io->helper('Progress');
        $this->schoolsTable = TableRegistry::getTableLocator()->get('Schools');

        $this->io->out('Collecting location information...');
        $this->districts = $this->getDistricts();
        $this->io->out(' - Done');
        $this->io->out();

        $this->io->out('Finding missing associations...');
        $this->patchMissingAssociations();
        $this->io->out();

        $dirtyCount = $this->getDirtyCount();

        if (!$dirtyCount) {
            $this->io->out('No districts need updated');

            return;
        }

        $continue = $this->io->askChoice(
            sprintf(
                'Fix missing associations for %s %s?',
                $dirtyCount,
                __n('district', 'districts', $dirtyCount)
            ),
            ['y', 'n'],
            'n'
        ) == 'y';

        if (!$continue) {
            return;
        }

        $this->saveDistricts();
    }

    /**
     * Returns a result set of school districts
     *
     * @return \Cake\Datasource\ResultSetInterface
     */
    private function getDistricts()
    {
        return $this->districtsTable->find()
            ->select(['id', 'name'])
            ->contain([
                'Schools' => function (Query $q) {
                    return $q
                        ->select(['id', 'name', 'school_district_id'])
                        ->contain(['Cities', 'Counties', 'States']);
                },
                'Cities' => function (Query $q) {
                    return $q->select(['id', 'name']);
                },
                'Counties' => function (Query $q) {
                    return $q->select(['id', 'name']);
                },
                'States' => function (Query $q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->all();
    }

    /**
     * Outputs details about what associations need to be added to this district
     *
     * @param SchoolDistrict $district School district entity
     * @param array $needsLinked Array of information about associations that need added
     * @return void
     */
    private function showDetails(SchoolDistrict $district, array $needsLinked)
    {
        $this->io->info(sprintf(
            ' - %s needs these associations added:',
            $district->name
        ));
        foreach ($needsLinked as $association => $geographies) {
            $displayedGeographies = [];
            foreach ($geographies as $geography) {
                if (in_array($geography['id'], $displayedGeographies)) {
                    continue;
                }
                $this->io->out(sprintf(
                    '   - %s: %s (location of %s)',
                    ucwords($association),
                    $geography['name'],
                    $geography['schoolName']
                ));
                $displayedGeographies[] = $geography['id'];
            }
        }
    }

    /**
     * Patches entities in $this->districts and adds missing geographic associations
     *
     * @return void
     */
    private function patchMissingAssociations()
    {
        $showDetails = $this->io->askChoice(
            'Show details of what associations need to be created?',
            ['y', 'n'],
            'y'
        ) == 'y';

        $this->progress->init([
            'total' => count($this->districts),
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach ($this->districts as $district) {
            $this->progress->increment(1)->draw();
            $needsLinked = [];
            foreach (['cities', 'counties', 'states'] as $association) {
                $alreadyLinkedGeographies = Hash::extract($district->$association, '{n}.id');
                $notLinkedGeographies = [];
                foreach ($district->schools as $school) {
                    foreach ($school->$association as $geography) {
                        if (in_array($geography->id, $alreadyLinkedGeographies)) {
                            continue;
                        }
                        $notLinkedGeographies[] = $geography->id;
                        $needsLinked[$association][] = [
                            'id' => $geography->id,
                            'name' => $geography->name,
                            'schoolName' => $school->name
                        ];
                    }
                }
                if (!$notLinkedGeographies) {
                    continue;
                }
                $ids = array_merge($alreadyLinkedGeographies, $notLinkedGeographies);
                $this->districtsTable->patchEntity(
                    $district,
                    [$association => [
                        '_ids' => array_unique($ids)
                    ]]
                );
            }

            if (!$needsLinked) {
                continue;
            }

            if ($showDetails) {
                $this->showDetails($district, $needsLinked);
            }
        }
    }

    /**
     * Returns a count of dirty districts
     *
     * @return int
     */
    private function getDirtyCount()
    {
        $dirtyCount = 0;
        foreach ($this->districts as $district) {
            if ($district->isDirty()) {
                $dirtyCount++;
            }
        }

        return $dirtyCount;
    }

    /**
     * Saves updated districts to the database
     *
     * @return void
     */
    private function saveDistricts()
    {
        $this->progress->init([
            'total' => count($this->districts),
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach ($this->districts as $district) {
            $this->progress->increment(1)->draw();
            if (!$district->isDirty()) {
                continue;
            }

            if ($this->districtsTable->save($district)) {
                continue;
            }

            $this->io->error('Error updating district #' . $district);
            print_r($district->getErrors());
            if (!$this->districtsTable->checkRules($district, 'update')) {
                $this->io->out('District fails application rule checks');
            }

            return;
        }
    }
}
