<?php
namespace pinfirestudios\yii1bugsnag;

use Yii;

class BugsnagComponent extends \CComponent 
{
    const IGNORED_LOG_CATEGORY = 'Bugsnag notified exception';

    public $bugsnag_api_key;

    public $releaseStage = null;
    public $notifyReleaseStages;

    public $filters = ['password'];

    protected $client;

    /**
     * True if we are in BugsnagLogTarget::export(), then don't trigger a flush, causing an
     * infinite loop
     * @var boolean
     */
    public $exportingLog = false;

    public function init()
    {
        if (empty($this->bugsnag_api_key))
        {
            throw new CHttpException(500, 'bugsnag_api_key must be set for the bugsnag component.');
        }

        $this->client = new \Bugsnag_Client($this->bugsnag_api_key);

        if (!empty($this->notifyReleaseStages))
        {
            $this->client->setNotifyReleaseStages($this->notifyReleaseStages);
        }

        $this->client->setFilters($this->filters);

        $this->client->setBatchSending(true);
        $this->client->setBeforeNotifyFunction([$this, 'beforeBugsnagNotify']);

        if (empty($this->releaseStage))
        {
            $this->releaseStage = defined('YII_ENV') ? YII_ENV : 'production';
        }

        $this->client->setAppVersion($this->getAppVersion());

        Yii::trace("Setting release stage to {$this->releaseStage}.", __CLASS__);
        $this->client->setReleaseStage($this->releaseStage);
    }

    /**
     * Returns user information
     *
     * @return array
     */
    public function getUserData()
    {
        // Don't crash if not using CWebUser
        if (!Yii::app()->hasComponent('user') || !isset(Yii::app()->user->id))
        {
            return null;
        }

        return [
            'id' => Yii::app()->user->id,
        ];
    }

    /**
     * Override this to provider version information to the JS function.
     *
     * @return string
     */
    public function getAppVersion()
    {
        return '';
    }

    public function getClient()
    {
        $clientUserData = $this->getUserData();
        if (!empty($clientUserData))
        {
            $this->client->setUser($clientUserData);
        }

        return $this->client;
    }

    public function beforeBugsnagNotify(\Bugsnag_Error $error)
    {
        if (!$this->exportingLog)
        {
            Yii::getLogger()->flush(true);
        }

        if (isset($error->stacktrace))
        {
            $trace = $error->stacktrace;

            if (!empty($trace->frames))
            {
                $rekey = false;
                for ($i = 0; $i < count($trace->frames); $i++)
                {
                    $frame = $trace->frames[$i];
                    $classDelimiter = strpos($frame['method'], '::');
                    if ($classDelimiter === false)
                    {
                        break;
                    }

                    $class = substr($frame['method'], 0, $classDelimiter);
                    if (
                        $frame['method'] != 'CApplication::handleError' &&
                        !is_a($class, 'CErrorHandler', true)
                    )
                    {
                        break;
                    }

                    unset($trace->frames[$i]);
                    $rekey = true;
                }

                if ($rekey)
                {
                    $trace->frames = array_values($trace->frames);
                }
            }
        }

        $error->setMetaData([
            'logs' => BugsnagLogTarget::getMessages(),
        ]);
    }

    public function notifyError($category, $message, $trace = null)
    {
        $this->getClient()->notifyError($category, $message, ['trace' => $trace], 'error');
    }

    public function notifyWarning($category, $message, $trace = null)
    {
        $this->getClient()->notifyError($category, $message, ['trace' => $trace], 'warning');
    }

    public function notifyInfo($category, $message, $trace = null)
    {
        $this->getClient()->notifyError($category, $message, ['trace' => $trace], 'info');
    }

    public function notifyException($exception, $severity = null)
    {
        $metadata = null;
        if ($exception instanceof BugsnagCustomMetadataInterface)
        {
            $metadata = $exception->getMetadata();
        }

        if ($exception instanceof BugsnagCustomContextInterface)
        {
            $this->getClient()->setContext($exception->getContext());
        }

        $this->getClient()->notifyException($exception, $metadata, $severity);
    }

    public function runShutdownHandler()
    {
        if (!$this->exportingLog)
        {
            Yii::getLogger()->flush(true);
        }

        $this->getClient()->shutdownHandler();
    }
}
