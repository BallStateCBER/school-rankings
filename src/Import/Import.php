<?php
namespace App\Import;

use App\Model\Table\ImportedFilesTable;
use Cake\Filesystem\Folder;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;

class Import
{
    /**
     * Returns an array of sets of files, grouped by year, including both filename and date of last import
     *
     * @return array
     */
    public function getFiles()
    {
        /** @var ImportedFilesTable $importedFilesTable */
        $importedFilesTable = TableRegistry::get('ImportedFiles');
        $dataPath = ROOT . DS . 'data';
        $dir = new Folder($dataPath);
        $subdirs = $dir->subdirectories($dir->path, false);
        $retval = [];

        foreach ($subdirs as $year) {
            if (!$this->isYear($year)) {
                throw new InternalErrorException('Directory ' . $dataPath . DS . $year . ' is not a year.');
            }
            $subdir = new Folder($dataPath . DS . $year);
            $files = $subdir->find('.*.xlsx');
            if (!$files) {
                continue;
            }
            foreach ($files as $file) {
                $retval[$year][] = [
                    'filename' => $file,
                    'imported' => $importedFilesTable->getImportDate($year . DS . $file)
                ];
            }
        }

        return $retval;
    }

    /**
     * Returns true or false, indicating if $string appears to be a year
     *
     * @param string $string String to be tested
     * @return bool
     */
    private function isYear($string)
    {
        return strlen($string) == 4 && is_numeric($string);
    }

    /**
     * Returns an array of years corresponding to subdirectories of /data
     *
     * @return array
     */
    public function getYears()
    {
        $dataPath = ROOT . DS . 'data';
        $dir = new Folder($dataPath);
        $years = $dir->subdirectories($dir->path, false);
        sort($years);

        return $years;
    }
}
