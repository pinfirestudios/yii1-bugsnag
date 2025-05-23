<?php
namespace pinfirestudios\yii1bugsnag;

use Yii;
use CHttpException;

class BugsnagComponent extends \CComponent 
{
    const IGNORED_LOG_CATEGORY = 'Bugsnag notified exception';

	public $bugsnag_api_key;
	public $bugsnag_api_key_for_js;

    public $releaseStage = null;
    public $notifyReleaseStages;

    public $notifyEndpoint = null;
    public $sessionsEndpoint = null;
	public $perfEndpoint = 'https://otlp.bugsnag.com';

    public $filters = ['password'];

    protected $client;

    /**
     * True if we are in BugsnagLogTarget::export(), then don't trigger a flush, causing an
     * infinite loop
     * @var boolean
     */
    public $exportingLog = false;

    /**
     * If we are already processing an exception, this signals the exception
     * error log category
     *
     * @see CApplication::handleException
     * @var string
     */
    public $currentExceptionLogCategory = null;

    /**
     * Sets @application as the project root and "strip path".
     * @var boolean
     */
    public $useAppAliasForProjectRoot = true;

    /**
     * Can be overridden to prevent the JS code being injected on pages
     * For example, we don't care if bots have JS errors...
     *
     * @return boolean true if we should include JS, false if not.
     */
    public function getShouldIncludeJs()
    {
        return true;
    }

    public function init()
    {
        if (empty($this->bugsnag_api_key))
        {
            throw new CHttpException(500, 'bugsnag_api_key must be set for the bugsnag component.');
		}

		if (empty($this->bugsnag_api_key_for_js))
		{
			$this->bugsnag_api_key_for_js = $this->bugsnag_api_key;
		}

        $this->client = new \Bugsnag_Client($this->bugsnag_api_key);

        if (isset($this->notifyEndpoint))
        {
            $this->client->setEndpoint($this->notifyEndpoint);
        }

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

        if ($this->useAppAliasForProjectRoot)
        {
            $basePath = Yii::app()->getBasePath();
            $this->client->setProjectRoot($basePath);
            $this->client->setStripPath($basePath);
        }

        $this->client->setType(get_class(Yii::app()));
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
        if ($category == $this->currentExceptionLogCategory)
        {
            return;
        }

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

    public function notifyException($exception, $severity = 'error')
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

        // Avoid sending exceptions as log messages and real exceptions
        // @see CApplication::handleException
        try
        {
            $this->currentExceptionLogCategory = 'exception.'.get_class($exception);
            if ($exception instanceof CHttpException)
            {
                $this->currentExceptionLogCategory .= '.' . $exception->statusCode;
            }

            $this->getClient()->notifyException($exception, $metadata, $severity);
        }
        finally
        {
            $this->currentExceptionLogCategory = null;
        }
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
