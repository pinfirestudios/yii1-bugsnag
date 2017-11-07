<?php
namespace pinfirestudios\yii1bugsnag;

use Yii;
use CLogger;

class BugsnagLogTarget extends \CLogRoute
{
    protected static $exportedMessages = [];

	/**
	 * @inheritdoc
	 */
    protected function processLogs(array $logs)
    {
        self::$exportedMessages = array_merge(self::$exportedMessages, $logs);

        Yii::app()->bugsnag->exportingLog = true;
        try
        {
            foreach ($logs as $message)
            {
                list($message, $level, $category, $timestamp) = $message; 
                
                if ($category == BugsnagComponent::IGNORED_LOG_CATEGORY) 
                {
                    continue;
                }

                if ($level == CLogger::LEVEL_ERROR)
                {
                    Yii::app()->bugsnag->notifyError($category, $message . " ($timestamp)", $trace);
                }
                elseif ($level == CLogger::LEVEL_WARNING)
                {
                    Yii::app()->bugsnag->notifyWarning($category, $message . " ($timestamp)", $trace);
                }
            }

            Yii::app()->bugsnag->exportingLog = false;
        }
        catch (\Exception $e)
        {
            Yii::app()->bugsnag->exportingLog = false;
            throw $e;
        }
    }

	/**
	 * Returns all collected messages, formatted as single strings.
	 *
	 * @return string[]
	 */
    public static function getMessages()
    {
        return array_map(
            function($message) use ($levelMap)
            {
                list($message, $level, $category, $timestamp) = $message;

                if (!is_string($message)) {
                    $message = print_r($message, true);
                }

                $date = date('Y-m-d H:i:s', $timestamp) . '.' . substr(fmod($timestamp, 1), 2, 4);
                return "{$level} - ({$category}) @ {$date} - {$message}";
            }, 
			self::$exportedMessages
        );
    }
}
