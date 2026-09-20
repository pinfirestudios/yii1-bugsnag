<?php
namespace pinfirestudios\yii1bugsnag;

/**
 * Allows an exception to override Bugsnag's default error grouping.
 */
interface BugsnagCustomGroupingInterface
{
    /**
     * @return string|null Custom grouping hash, or null to retain default grouping.
     */
    public function getGroupingHash();
}
