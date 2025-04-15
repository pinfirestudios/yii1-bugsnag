<?php

namespace pinfirestudios\yii1bugsnag;

use Yii;
use CClientScript;
use CHttpException;
use CJavaScript;
use Throwable;

/**
 * If you would like to use Bugsnag's javascript on your site, add this widget to your base layout.
 * This will automatically register Bugsnag's javascript to the page.  Default version is 4.
 * <pre>
 *     $this->widget(\pinfirestudios\yii1bugnsag\BugsnagJsWidget::class);
 * </pre>
 */
class BugsnagJsWidget extends \CWidget
{
    /** @var integer Bugsnag javascript version */
    public $version = 7;

    /** @var boolean Use the Cloudfront CDN (which will have CORS issues @see https://github.com/bugsnag/bugsnag-js/issues/155 */
	public $useCdn = false;

    /** @var boolean Automatically track sessions */
	public $autoTrackSessions = true;

    /** @var string Source alias for the Bugsnag javascript file */
    public $sourceAlias = 'npm.@bugsnag.browser.dist';

    /** @var string Source alias for the Bugsnag performance javascript file */
    public $performanceSourceAlias = 'npm.@bugsnag.browser-performance.dist';

    /** @var string Alternate filename for the Bugsnag javascript file */
    public $alternateFilename = 'bug-reporting.js';

    /** @var string Alternate filename for the Bugsnag performance javascript file */
    public $performanceAlternateFilename = 'performance.js';

    /** @var bool Include bugsnag performance */
    public $includePerformance = true;

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
			try
			{
				$this->registerJavascript();
			}
			catch (Throwable $t)
			{
				Yii::error(__METHOD__, "Error(s) trying to publish Bugsnag JS: " . $t->getMessage());
			}
        }

        if ($this->includePerformance)
        {
            $this->registerPerformance();
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
            $bugsnagUrl = $this->publishBugsnagJs();
        }

        $cs = Yii::app()->clientScript;

        $cs->registerScriptFile(
            $bugsnagUrl,
            CClientScript::POS_HEAD,
        );

        $options = [
            'apiKey' => Yii::app()->bugsnag->bugsnag_api_key_for_js,
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

    private function publishBugsnagJs()
    {
        // Yii-1 won't have a vendor or bower-asset path guaranteed, so try and figure it out
        // with a relative path.
        $oldLinkAssets = Yii::app()->assetManager->linkAssets;
        Yii::app()->assetManager->linkAssets = false;
        $sourceUrl = Yii::app()->assetManager->publish(
            Yii::getPathOfAlias($this->sourceAlias)
        );
        Yii::app()->assetManager->linkAssets = $oldLinkAssets;

        if (YII_DEBUG) {
            $filename = 'bugsnag.js';
        } else {
            $filename = 'bugsnag.min.js';
        }

        $basePath = Yii::getPathOfAlias('webroot');

        // Copy to an alternate name to try and get around some adblockers
        $newFilename = $this->alternateFilename;
        Yii::trace("Copying Bugsnag JS file to: " . $newFilename, __METHOD__);
        $newUrl = $sourceUrl . '/' . $newFilename;
        Yii::trace("New URL: " . $newUrl, __METHOD__);
        $newFilePath = $basePath . $newUrl;
        Yii::trace("New file path: " . $newFilePath, __METHOD__);

        if (file_exists($newFilePath)) {
            return $newUrl;
        };

        $oldUrl = $sourceUrl . '/' . $filename;
        $oldFilePath = $basePath . $oldUrl;

        if (!file_exists($oldFilePath)) {
            Yii::warning("Bugsnag JS file not found: " . $oldFilePath, __METHOD__);
            return null;
        }

        try {
            if (!copy($oldFilePath, $newFilePath)) {
                Yii::warning("Failed to copy Bugsnag JS file: from " . $oldFilePath . " to " . $newFilePath, __METHOD__);
                return $oldUrl;
            }
        } catch (Throwable $t) {
            Yii::warning("Failed to copy Bugsnag JS file: from " . $oldFilePath . " to " . $newFilePath . " " . $t->getMessage(), __METHOD__);
            return $oldUrl;
        }

        return $newUrl;
    }

    private function registerPerformance()
    {
        $performanceUrl = 'https://d2wy8f7a9ursnm.cloudfront.net/v2.12.0/bugsnag-performance.min.js';
        
        if (!$this->useCdn)
        {
            $performanceUrl = $this->publishPerformanceJs($performanceUrl);
        }
        
        $cs = Yii::app()->clientScript;
        
        $options = [
            'apiKey' => Yii::app()->bugsnag->bugsnag_api_key_for_js,
            'appVersion' => Yii::app()->bugsnag->appVersion,
            'releaseStage' => Yii::app()->bugsnag->releaseStage,
            'endpoint' => 'https://bugs-otlp.rechub.net/v1/traces',
        ];
        
        $encodedOptions = CJavaScript::encode($options);
        $js = <<<JS
import BugsnagPerformance from '{$performanceUrl}'
BugsnagPerformance.start({$encodedOptions})
JS;
        $cs->registerScript(__CLASS__ . 'Performance', $js, CClientScript::POS_HEAD, ['type' => 'module']);
    }
    
    private function publishPerformanceJs($cdnUrl)
    {
        $runtimeDir = Yii::app()->runtimePath;
        $destinationDir = $runtimeDir . '/browser-performance';
        $destinationPath = $destinationDir . '/' . $this->performanceAlternateFilename;

        if (!file_exists($destinationPath)) {
            try {
                $js = file_get_contents($cdnUrl);

                if (!file_exists($destinationDir)) {
                    mkdir($destinationDir, 0777, true);
                }

                file_put_contents($destinationPath, $js);
            } catch (Throwable $t) {
                Yii::warning("Failed to copy Bugsnag Performance JS file: from " . $cdnUrl . " to " . $destinationPath . " " . $t->getMessage(), __METHOD__);
                return null;
            }
        }

        $publishedUrl = Yii::app()->assetManager->publish($destinationDir);
        return $publishedUrl . '/' . $this->performanceAlternateFilename;
    }
}