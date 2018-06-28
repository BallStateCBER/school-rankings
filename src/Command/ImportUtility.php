<?php
namespace App\Command;

use App\Model\Table\ImportedFilesTable;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;

class ImportUtility
{
    /**
     * Returns an array of sets of files, grouped by year, including both filename and date of last import
     *
     * @return array
     */
    public function getFiles()
    {
        /** @var ImportedFilesTable $importedFilesTable */
        $importedFilesTable = TableRegistry::getTableLocator()->get('ImportedFiles');
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
                    'imported' => $importedFilesTable->getImportDate($year, $file)
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
    public function isYear($string)
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

    /**
     * Returns a suggested metric name, given information about an import spreadsheet column
     *
     * @param string $filename Import file name
     * @param string $worksheetName Name of active worksheet in import file
     * @param array $unknownMetric Array of information about the name and group of a column
     * @return string
     */
    public function getSuggestedName($filename, $worksheetName, $unknownMetric)
    {
        // Start with filename
        $suggestedNameParts = [explode('.', $filename)[0]];

        // Add worksheet name (unless if it's a year)
        if (!$this->isYear($worksheetName)) {
            $suggestedNameParts[] = trim($worksheetName);
        }

        // Clean up and add the column name
        $columnName = $unknownMetric['name'];
        $cleanColumnName = $columnName;
        while (strpos($cleanColumnName, '  ') !== false) {
            $cleanColumnName = str_replace("\n", ' ', $cleanColumnName);
            $cleanColumnName = str_replace('  ', ' ', $cleanColumnName);
        }
        $suggestedNameParts[] = trim($cleanColumnName);

        // Add group, if applicable
        if ($unknownMetric['group']) {
            $suggestedNameParts[] = $unknownMetric['group'];
        }

        // Remove blank and repeated parts
        foreach ($suggestedNameParts as $i => $namePart) {
            if ($namePart == '') {
                unset($suggestedNameParts[$i]);
                continue;
            }
            if ($i > 0 && $suggestedNameParts[$i - 1] == $namePart) {
                unset($suggestedNameParts[$i]);
                continue;
            }
        }

        $nameDelimiter = ' > ';

        return implode($nameDelimiter, $suggestedNameParts);
    }
}
