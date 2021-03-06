<?php

namespace App\Models;

/**
 * Module is modeled on the 'modules' array contained in Moodle's REST API response to wsfunction
 * core_course_get_contents. $completionStatus is not contained in the response.
 *
 * Class Module
 * @package App\Models
 */
class Module
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $instance;

    /**
     * @var int
     */
    public $visible;

    /**
     * @var int
     */
    public $visibleoncoursepage;

    /**
     * @var string
     */
    public $modicon;

    /**
     * @var string
     */
    public $modname;

    /**
     * @var string
     *
     */
    public $modplural;

    /**
     * @var CompletionStatus
     */
    public $completionStatus;

}