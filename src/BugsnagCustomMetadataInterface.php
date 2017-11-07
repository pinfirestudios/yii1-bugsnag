<?php
namespace pinfirestudios\yii1bugsnag;

/**
 * Allows an exception to set custom metadata to pass to Bugsnag
 */
interface BugsnagCustomMetadataInterface 
{
    /**
     * Gets metadata for this exception
     * @return array
     */
    public function getMetadata();
}
