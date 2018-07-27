<?php
namespace App\Model\Entity;

use Cake\Http\Exception\InternalErrorException;

/**
 * Class RankableTrait
 * @package App\Model\Entity
 * @property string $dataCompleteness
 * @property float $score
 */
trait RankableTrait
{
    /**
     * A string ('full', 'partial', or 'empty') indicating whether this school/district, as part of a ranking result
     * set, has statistics for all, some, or none of the metrics associated with the current ranking formula
     *
     * @var string
     */
    private $dataCompleteness;

    private $score = 0;

    /**
     * Sets the dataCompleteness property
     *
     * @param string $dataCompleteness Either full, partial, or empty
     * @return void
     * @throws InternalErrorException
     */
    public function setDataCompleteness($dataCompleteness)
    {
        if (in_array($dataCompleteness, ['full', 'partial', 'empty'])) {
            $this->dataCompleteness = $dataCompleteness;

            return;
        }

        throw new InternalErrorException('Invalid data completeness string: ' . $dataCompleteness);
    }

    /**
     * Gets the value of the dataCompleteness property
     *
     * @return string
     * @throws InternalErrorException
     */
    public function getDataCompleteness()
    {
        if ($this->dataCompleteness) {
            return $this->dataCompleteness;
        }

        throw new InternalErrorException('School / district data completeness undetermined');
    }

    /**
     * Sets this entity's score
     *
     * @param int|float $score Score
     * @return void
     * @throws InternalErrorException
     */
    public function setScore($score)
    {
        if (is_numeric($score)) {
            $this->score = $score;

            return;
        }

        throw new InternalErrorException('Invalid, non-numeric score: ' . $score);
    }

    /**
     * Increments this entity's score
     *
     * @param int|float $amount Amount to increase the score
     * @return void
     */
    public function incrementScore($amount)
    {
        if (is_numeric($amount)) {
            $this->score += $amount;

            return;
        }

        throw new InternalErrorException('Invalid, non-numeric score increment: ' . $amount);
    }
}
