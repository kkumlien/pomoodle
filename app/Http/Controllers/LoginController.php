<?php

namespace App\Http\Controllers;

use App\Constants\SessionConstant;
use App\Services\DataMerge;
use App\Services\HttpJsonResponseService;
use App\Services\MoodleAuthentication;
use App\Services\MoodleDataRetrieval;
use App\Services\MoodleDataStorage;
use App\Services\MoodleSiteValidator;
use App\Utils\UrlBuilder;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonMapper;

class LoginController extends Controller
{

    /**
     * @var MoodleDataRetrieval
     */
    private $moodleDataRetrieval;

    /**
     * @var MoodleSiteValidator
     */
    private $moodleSiteValidator;

    /**
     * @var MoodleAuthentication
     */
    private $authenticationService;

    /**
     * @var MoodleDataStorage
     */
    private $moodleDataStorage;

    /**
     * @var DataMerge
     */
    private $dataMerge;

    /**
     * LoginController constructor.
     */
    public function __construct()
    {
        $httpJsonResponseService = new HttpJsonResponseService(new Client(), new JsonMapper());
        $this->moodleDataRetrieval = new MoodleDataRetrieval(new UrlBuilder(), $httpJsonResponseService);
        $this->moodleSiteValidator = new MoodleSiteValidator();
        $this->authenticationService = new MoodleAuthentication(new UrlBuilder(), $httpJsonResponseService);
        $this->moodleDataStorage = new MoodleDataStorage();
        $this->dataMerge = new DataMerge();
    }


    /**
     * Handles requests for displaying the login page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function loginPage()
    {
        return view('pages.login');
    }


    /**
     * Handles authenticating users for the application, retrieves the data from Moodle then redirects to your home
     * screen
     *
     * @param Request $request
     * @return $this
     */
    public function login(Request $request)
    {
        $moodleSite = $request->input('moodleSite');
        $username = $request->input('username');
        $password = $request->input('password');

        // Check if Moodle site is registered with the application
        $moodleSiteData = $this->moodleSiteValidator->validateMoodleSite($moodleSite);
        $moodleUrl = empty($moodleSiteData->site_url) ? null : $moodleSiteData->site_url;
        if ($moodleUrl == null) {
            return view('pages.login')->with('errorMessage', 'Moodle site not registered.');
        }

        // Log in to Moodle and return Moodle token that we use to retrieve data
        $wsToken = $this->authenticationService->authenticateUser($moodleUrl, $username, $password);
        if ($wsToken == null) {
            return view('pages.login')->with('errorMessage', 'Invalid user credentials.');
        }

        $user = $this->moodleDataRetrieval->getUserData($moodleUrl, $wsToken);
        Log::debug(print_r($user, true));

        // Create or update data in our local database, then merge it to the user object
        $userID = $this->moodleDataStorage->storeUserData($user, $moodleSiteData->site_id);
        $this->dataMerge->mergeActivityDuration($userID, $user);

        // We're authenticated, happy days!
        // Store user object and ID in the session
        session([SessionConstant::AUTH => true]);
        session([SessionConstant::USER => $user]);
        session([SessionConstant::USER_ID => $userID]);

        return redirect('student');

    }

}
