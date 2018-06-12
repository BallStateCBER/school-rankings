<?php
namespace App\Model\Context;

use Cake\Http\Exception\InternalErrorException;
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
    /**
     * Returns an array of all valid data contexts
     *
     * @return array
     */
    public static function getContexts()
    {
        return ['school', 'district'];
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
            'district' => 'school_district_id'
        ];

        if (array_key_exists($context, $locationFields)) {
            return $locationFields[$context];
        }

        throw new InternalErrorException('Unrecognized context: ' . $context);
    }
}
