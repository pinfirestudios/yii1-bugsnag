<?php

namespace pinfirestudios\yii1bugsnag;

use Yii;
use CClientScript;

/**
 * If you would like to use Bugsnag's javascript on your site, add this widget to your base layout.
 * This will automatically register Bugsnag's javascript to the page.  Default version is 3.
 * <pre>
 *     $this->widget(\pinfirestudios\yii1bugnsag\BugsnagJsWidget::class);
 * </pre>
 */
class BugsnagJsWidget extends \CWidget
{
    /**
     * @var integer Bugsnag javascript version
     */
    public $version = 3;

    /**
     * @type boolean Use the Cloudfront CDN (which will have CORS issues @see https://github.com/bugsnag/bugsnag-js/issues/155
     */
    public $useCdn = false;

    /**
     * Initiates Bugsnag javascript registration
     */
    public function init()
    {
        if (!Yii::app()->hasComponent('bugsnag'))
        {
            throw new InvalidConfigException('BugsnagAsset requires Bugsnag component to be enabled');
        }

        if (!in_array($this->version, [2, 3]))
        {
            throw new InvalidConfigException('Bugsnag javascript only supports version 2 or 3');
        }

        $this->registerJavascript();

        parent::init();
    }


    /**
     * Registers Bugsnag JavaScript to page
     */
    private function registerJavascript()
    {
        $filePath = '//d2wy8f7a9ursnm.cloudfront.net/bugsnag-' . $this->version . '.js';
        if (!$this->useCdn)
        {
            $this->sourcePath = '@bower/bugsnag/src';
			$filePath = 'bugsnag.js';

			if (!file_exists(Yii::getPathOfAlias($this->sourcePath . '/' . $filePath)))
			{
				throw new InvalidConfigException('Cannot find Bugsnag.js source code.  Is bower-asset/bugsnag installed?');
			}
        }

		$cs = Yii::app()->clientScript;
	   
		$cs->registerScriptFile(
			$filePath,
			CClientScript::POS_HEAD,
			[
				'data-apikey' => Yii::app()->bugsnag->bugsnag_api_key,
				'data-releasestage' => Yii::app()->bugsnag->releaseStage,
				'data-appversion' => Yii::app()->version,
        	]
		);

        // Include this wrapper since bugsnag.js might be blocked by adblockers.  We don't want to completely die if so.
        $js = 'var Bugsnag = Bugsnag || {};';

        if (!Yii::app()->user->isGuest)
        {
            $userId = CJavaScript::encode(Yii::app()->user->id);
            $js .= "Bugsnag.user = { id: $userId };";
        }

        if (!empty(Yii::app()->bugsnag->notifyReleaseStages))
        {
            $releaseStages = CJavaScript::encode(Yii::app()->bugsnag->notifyReleaseStages);
            $js .= "Bugsnag.notifyReleaseStages = $releaseStages;";
		}

		$cs->registerScript(__CLASS__, $js, CClientScript::POS_HEAD);
    }
}
