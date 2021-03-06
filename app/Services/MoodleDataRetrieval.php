<?php

namespace App\Services;


use App\Exceptions\MoodleSiteException;
use App\Models\ActivitiesCompletionStatus;
use App\Models\Course;
use App\Models\Topic;
use App\Models\User;
use App\Utils\UrlBuilder;
use Exception;

/**
 * Retrieves data from Moodle using its REST API.
 *
 * Class MoodleDataRetrieval
 * @package App\Services
 */
class MoodleDataRetrieval
{
    /**
     * @var UrlBuilder
     */
    private $moodleUrlBuilder;

    /**
     * @var HttpJsonResponseService
     */
    private $responseService;

    private const MOODLE_REST_SERVICE = '/webservice/rest/server.php';


    /**
     * MoodleDataRetrieval constructor.
     *
     * @param UrlBuilder $moodleUrlBuilder
     * @param HttpJsonResponseService $responseService
     */
    public function __construct(UrlBuilder $moodleUrlBuilder, HttpJsonResponseService $responseService)
    {
        $this->moodleUrlBuilder = $moodleUrlBuilder;
        $this->responseService = $responseService;
    }


    /**
     * Retrieves user data from Moodle containing user and course information.
     *
     * @param string $moodleUrl
     * @param string $wsToken
     * @return User|object
     */
    public function getUserData(string $moodleUrl, string $wsToken)
    {
        $this->moodleUrlBuilder
            ->newUrl($moodleUrl . self::MOODLE_REST_SERVICE)
            ->withAlways('wstoken', $wsToken)
            ->withAlways('moodlewsrestformat', 'json');

        try {
            $user = $this->getUserInfo();
            $user->courses = $this->getUserCourses($user->userid);
            $this->populateUserCourseData($user);
        } catch (Exception $exception) {
            throw new MoodleSiteException();
        }

        return $user;
    }


    /**
     * @return User|object
     */
    private function getUserInfo()
    {
        $url = $this->moodleUrlBuilder
            ->with('wsfunction', 'core_webservice_get_site_info')
            ->build();

        $user = $this->responseService->getResponseAsObject($url, new User());

        return $user;
    }


    /**
     * @param int $userId
     * @return Course[]
     */
    private function getUserCourses(int $userId)
    {
        $url = $this->moodleUrlBuilder
            ->with('wsfunction', 'core_enrol_get_users_courses')
            ->with('userid', $userId)
            ->build();

        $courses = $this->responseService->getResponseAsObjectArray($url, 'App\Models\Course');

        return $courses;
    }


    /**
     * Add the course data from Moodle to the User object.
     * @param User $user
     */
    private function populateUserCourseData($user)
    {
        foreach ($user->courses as $course) {
            $topics = $this->getCourseContent($course->id);

            $activitiesCompletionStatus = $this->getActivitiesCompletionStatus($user->userid, $course->id);

            $topics = $this->mergeActivitiesCompletionStatusToTopics($activitiesCompletionStatus, $topics);

            $course->topics = $topics;
        }
    }


    /**
     * @param int $courseId
     * @return Topic[]
     */
    private function getCourseContent(int $courseId)
    {
        $url = $this->moodleUrlBuilder
            ->with('wsfunction', 'core_course_get_contents')
            ->with('courseid', $courseId)
            ->build();

        $topics = $this->responseService->getResponseAsObjectArray($url, 'App\Models\Topic');

        $topics = $this->removeGeneralTopic($topics);

        return $topics;
    }

    /**
     * Moodle adds a General topic to the start of all Courses. This removes the General topic from the topic array.
     *
     * @param Topic[] $topics
     * @return array
     */
    private function removeGeneralTopic(array $topics)
    {
        array_shift($topics);
        return $topics;
    }


    /**
     * @param int $userId
     * @param int $courseId
     * @return ActivitiesCompletionStatus|object
     */
    private function getActivitiesCompletionStatus(int $userId, int $courseId)
    {
        $url = $this->moodleUrlBuilder
            ->with('wsfunction', 'core_completion_get_activities_completion_status')
            ->with('userid', $userId)
            ->with('courseid', $courseId)
            ->build();

        $activitiesCompletionStatus = $this->responseService->getResponseAsObject($url, new ActivitiesCompletionStatus());

        return $activitiesCompletionStatus;
    }


    /**
     * @param ActivitiesCompletionStatus $activitiesCompletionStatus
     * @param array $topics
     * @return array
     */
    private function mergeActivitiesCompletionStatusToTopics(ActivitiesCompletionStatus $activitiesCompletionStatus, array $topics)
    {
        $completionStatuses = $activitiesCompletionStatus->statuses;

        foreach ($topics as $topic) {
            $modules = $topic->modules;

            foreach ($modules as $module) {
                foreach ($completionStatuses as $completionStatus) {
                    if ($module->id == $completionStatus->cmid) {
                        $module->completionStatus = $completionStatus;
                    }

                }
            }
        }

        return $topics;
    }

}