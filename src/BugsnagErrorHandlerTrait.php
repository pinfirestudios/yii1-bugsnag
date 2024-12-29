<?php
namespace pinfirestudios\yii1bugsnag;

use Yii;

/**
 * Adds bugsnag error handling to classes deriving from \yii\base\ErrorHandler
 */
trait BugsnagErrorHandlerTrait
{
    /**
     * Tracks if we are in the exception handler and have already notified Bugsnag about
     * the exception
     *
     * @var boolean
     */
    protected $inExceptionHandler = false;

    /**
     * Only log the exception here if we haven't handled it below (in handleException)
     */
    public function logException($exception)
    {
        if (!$this->inExceptionHandler)
        {
            Yii::app()->bugsnag->notifyException($exception);
        }

        try
        {
            Yii::error("Caught exception " . $exception::class . ": " . (string)$exception, BugsnagComponent::IGNORED_LOG_CATEGORY);
        }
        catch (\Exception) {}
    }

    /**
     * Ensures logs are written to the DB if an exception occurs
     */
    public function handleException($exception)
    {
        Yii::app()->bugsnag->notifyException($exception);
        $this->inExceptionHandler = true;

        parent::handleException($exception);
    }
}
