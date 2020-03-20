<?php
namespace App\Model\Context;

use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Class Context
 *
 * Used for managing the separation of school metrics and statistics versus district metrics and statistics
 *
 * @package App\Model\Context
 */
class Context
{
    public const SCHOOL_CONTEXT = 'school';
    public const DISTRICT_CONTEXT = 'district';

    /**
     * Returns an array of all valid data contexts
     *
     * @return array
     */
    public static function getContexts()
    {
        return [self::SCHOOL_CONTEXT, self::DISTRICT_CONTEXT];
    }

    /**
     * Returns whether or not the provided context is valid
     *
     * @param string $context Data context, such as 'school' or 'district'
     * @return bool
     */
    public static function isValid($context)
    {
        return in_array($context, self::getContexts());
    }

    /**
     * Returns TRUE provided context is valid or throws an exception
     *
     * @param string $context Data context, such as 'school' or 'district'
     * @return bool
     * @throws Exception
     */
    public static function isValidOrFail($context)
    {
        if (in_array($context, self::getContexts())) {
            return true;
        }

        throw new Exception('Unrecognized context: ' . $context);
    }

    /**
     * Returns the database field name associated with appropriate location ID
     *
     * @param string $context Data context
     * @return mixed
     */
    public static function getLocationField($context)
    {
        $locationFields = [
            'school' => 'school_id',
            'district' => 'school_district_id',
        ];

        if (array_key_exists($context, $locationFields)) {
            return $locationFields[$context];
        }

        throw new InternalErrorException('Unrecognized context: ' . $context);
    }

    /**
     * @param string $context Either 'school' or 'district'
     *
     * @throws Exception
     * @return Table|bool
     */
    public static function getTable($context)
    {
        if (!self::isValidOrFail($context)) {
            return false;
        }

        if ($context == self::SCHOOL_CONTEXT) {
            return TableRegistry::getTableLocator()->get('Schools');
        }

        return TableRegistry::getTableLocator()->get('SchoolDistricts');
    }
}
