<?php
namespace pinfirestudios\yii1bugsnag;

use Yii;
use CLogger;

class BugsnagLogTarget extends \CLogRoute
{
    /**
     * @var string[] Error message categories for which we should NOT notify Bugsnag,
     *     even if they are errors or warnings.
     */
    public $noNotifyCategories = [];

    /**
     * @var int Maximum number of recent log messages to retain for attaching as
     *     "recent logs before the error" metadata on a Bugsnag report. These
     *     messages act like breadcrumbs: only the most recent context matters,
     *     so the buffer is kept bounded to this many entries. Keeping it bounded
     *     prevents unbounded per-process memory growth during long, query-heavy
     *     runs (e.g. CLI migrations issuing 100k+ trace-logged SQL statements).
     *     Set to 0 or a negative value to disable trimming (unbounded — not
     *     recommended). Configurable via the log route config in main.php.
     */
    public $maxExportedMessages = 100;

    protected static $exportedMessages = [];

    /**
     * @inheritdoc
     */
    protected function processLogs($logs)
    {
        self::$exportedMessages = array_merge(self::$exportedMessages, $logs);

        // Bound the retained buffer to the most recent N messages so it behaves
        // like a breadcrumb ring buffer instead of accumulating every log line
        // for the entire process lifetime.
        if ($this->maxExportedMessages > 0)
        {
            $overflow = count(self::$exportedMessages) - $this->maxExportedMessages;
            if ($overflow > 0)
            {
                self::$exportedMessages = array_slice(self::$exportedMessages, $overflow);
            }
        }

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

                if (!in_array($category, $this->noNotifyCategories))
                {
                    if ($level == CLogger::LEVEL_ERROR)
                    {
                        Yii::app()->bugsnag->notifyError($category, $message . " ($timestamp)");
                    }
                    elseif ($level == CLogger::LEVEL_WARNING)
                    {
                        Yii::app()->bugsnag->notifyWarning($category, $message . " ($timestamp)");
                    }
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
            function($message)
            {
                list($message, $level, $category, $timestamp) = $message;

                if (!is_string($message)) {
                    $message = print_r($message, true);
                }

                $date = date('Y-m-d H:i:s', (int)$timestamp) . '.' . substr(fmod($timestamp, 1), 2, 4);
                return "{$level} - ({$category}) @ {$date} - {$message}";
            }, 
            self::$exportedMessages
        );
    }
}
