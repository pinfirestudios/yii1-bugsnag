<?php

namespace pinfirestudios\yii1bugsnag;

use Yii;
use CClientScript;
use CHttpException;
use CJavaScript;

/**
 * If you would like to use Bugsnag's javascript on your site, add this widget to your base layout.
 * This will automatically register Bugsnag's javascript to the page.  Default version is 4.
 * <pre>
 *     $this->widget(\pinfirestudios\yii1bugnsag\BugsnagJsWidget::class);
 * </pre>
 */
class BugsnagJsWidget extends \CWidget
{
    /**
     * @var integer Bugsnag javascript version
     */
    public $version = 7;

    /**
     * @type boolean Use the Cloudfront CDN (which will have CORS issues @see https://github.com/bugsnag/bugsnag-js/issues/155
     */
	public $useCdn = false;

	/**
	 * @var bool
	 */
	public $autoTrackSessions = false;

    public $sourceAlias = 'npm.@bugsnag.browser.dist';

    /**
     * Initiates Bugsnag javascript registration
     */
    public function init()
    {
        if (!Yii::app()->hasComponent('bugsnag'))
        {
            throw new CHttpException(500, 'BugsnagAsset requires Bugsnag component to be enabled');
        }

        if (!in_array($this->version, [7]))
        {
            throw new CHttpException(500, 'Bugsnag javascript only supports version 7');
        }

        if (Yii::app()->bugsnag->shouldIncludeJs)
        {
            $this->registerJavascript();
        }

        parent::init();
    }

    /**
     * Registers Bugsnag JavaScript to page
     */
    private function registerJavascript()
    {
        $bugsnagUrl = '//d2wy8f7a9ursnm.cloudfront.net/bugsnag-' . $this->version . '.js';
        if (!$this->useCdn)
        {
            // Yii-1 won't have a vendor or bower-asset path guaranteed, so try and figure it out
            // with a relative path.
            $sourcePath = Yii::getPathOfAlias($this->sourceAlias);
            $sourcePath = Yii::app()->assetManager->publish($sourcePath);
            $filePath = 'bugsnag.js';//min.js';

            $bugsnagUrl = $sourcePath . '/' . $filePath;

            // Copy to an alternate name to try and get around some adblockers
            $newFilename = 'bug-reporting.js';
            $newBugsnagUrl = $sourcePath . '/' . $newFilename;

            $webroot = Yii::getPathOfAlias('webroot');
            $newFile = $webroot . '/' . $newBugsnagUrl;
            if (!file_exists($newFile))
            {
                $oldFile = $webroot . '/' . $bugsnagUrl;
                copy($oldFile, $newFile);
            }

            $bugsnagUrl = $newBugsnagUrl;
        }

        $cs = Yii::app()->clientScript;

        $cs->registerScriptFile(
            $bugsnagUrl,
            CClientScript::POS_HEAD,
        );

        $options = [
            'apiKey' => Yii::app()->bugsnag->bugsnag_api_key,
            'releaseStage' => Yii::app()->bugsnag->releaseStage,
			'appVersion' => Yii::app()->bugsnag->appVersion,
			'autoTrackSessions' => $this->autoTrackSessions,
        ];

        if (
            isset(Yii::app()->bugsnag->notifyEndpoint) ||
            isset(Yii::app()->bugsnag->sessionsEndpoint)
        )
        {
            $options['endpoints'] = [];
            if (isset(Yii::app()->bugsnag->notifyEndpoint))
            {
                $options['endpoints']['notify'] = Yii::app()->bugsnag->notifyEndpoint;
            }

            if (isset(Yii::app()->bugsnag->sessionsEndpoint))
            {
                $options['endpoints']['sessions'] = Yii::app()->bugsnag->sessionsEndpoint;
            }
        }

        if (!Yii::app()->user->isGuest)
        {
            $options['user'] = [
                'id' => Yii::app()->user->id,
            ];
        }

        if (!empty(Yii::app()->bugsnag->notifyReleaseStages))
        {
            $options['notifyReleaseStages'] = Yii::app()->bugsnag->notifyReleaseStages;
        }

        $js = 'if (typeof(Bugsnag) != "undefined") { Bugsnag.start(' . CJavaScript::encode($options) . '); }';

        $cs->registerScript(__CLASS__, $js, CClientScript::POS_HEAD);
    }
}
