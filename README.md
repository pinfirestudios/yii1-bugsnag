# Yii1 Bugsnag integration
To use, configure as such:

    $config = [
        'components' => [
            'errorHandler' => [
                'class' => 'pinfirestudios\yii1bugsnag\BugsnagErrorHandler' 
            ],
            'bugsnag' => [
                'class' => 'pinfirestudios\yii1bugsnag\BugsnagComponent', // Or your override of such
                'bugsnag_api_key' => 'YOUR API KEY',
                'notifyReleaseStages' => ['staging', 'production'],
            ],
            'log' => [
				'class' => 'CLogRouter',
                'routes' => [
                    [
                        'class' => 'pinfirestudios\yii1bugsnag\BugsnagLogTarget',
                        'levels' => 'error, warning, info, trace',
                    ]
                ],
            ],
        ],
    ];

If you would like to use Bugsnag's javascript on your site, you'll need to install *bower-asset/bugsnag*:

1. Add the following to your project's composer.json

    "repositories": [
        {
            "type": "composer",
            "url": "https://asset-packagist.org"
        }
    ]

2. Require bower-asset/bugsnag

    composer require bower-asset/bugsnag

3. Once you have it installed, add the BugsnagJsWidget to your default layout.  This will automatically register Bugsnag's javascript to the page.  Default version is 3.
 
    $this->widget(\pinfirestudios\yii1bugsnag\BugsnagJsWidget::class);

If you need to use version 2 of Bugsnag's javascript, you can specify the version in your widget configuration.
