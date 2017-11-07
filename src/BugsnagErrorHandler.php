<?php
namespace pinfirestudios\yii1bugsnag;

/**
 * Handles exceptions in web applications
 */
class BugsnagErrorHandler extends \CErrorHandler
{
    use BugsnagErrorHandlerTrait;
}
