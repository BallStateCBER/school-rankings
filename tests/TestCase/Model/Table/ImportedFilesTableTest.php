<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ImportedFilesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\ImportedFilesTable Test Case
 */
class ImportedFilesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\ImportedFilesTable
     */
    public $ImportedFiles;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.imported_files'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('ImportedFiles') ? [] : ['className' => ImportedFilesTable::class];
        $this->ImportedFiles = TableRegistry::getTableLocator()->get('ImportedFiles', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->ImportedFiles);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
