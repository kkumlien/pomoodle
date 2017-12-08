<?php

namespace App\Http\Controllers;

use App\Constants\SessionConstant;
use App\Services\HttpJsonResponseService;
use App\Services\MoodleAuthentication;
use App\Services\MoodleDataRetrieval;
use App\Services\MoodleSiteValidator;
use App\Utils\UrlBuilder;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use JsonMapper;
use Validator;


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
     * LoginController constructor.
     */
    public function __construct()
    {
        $httpJsonResponseService = new HttpJsonResponseService(new Client(), new JsonMapper());
        $this->moodleDataRetrieval = new MoodleDataRetrieval(new UrlBuilder(), $httpJsonResponseService);
        $this->moodleSiteValidator = new MoodleSiteValidator();
        $this->authenticationService = new MoodleAuthentication(new UrlBuilder(), $httpJsonResponseService);
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
     * Handles authenticating users for the application and redirecting them to your home screen
     *
     * @param Request $request
     * @return $this
     */
    public function login(Request $request)
    {
        $moodleSite = $request->input('moodleSite');
        $username = $request->input('username');
        $password = $request->input('password');

        $moodleUrl = $this->moodleSiteValidator->validateMoodleSite($moodleSite);

        if ($moodleUrl == null) {
            return view('pages.login')->with('errorMessage', 'Moodle site not registered.');
        }

        $wsToken = $this->authenticationService->authenticateUser($moodleUrl, $username, $password);

        if ($wsToken == null) {
            return view('pages.login')->with('errorMessage', 'Invalid user credentials');
        }

        session([SessionConstant::AUTH => true]);

        $user = $this->moodleDataRetrieval->getUserData($moodleUrl, $wsToken);

        //TODO - check if user exists in our database
        //TODO - populate database with moodle data

        session([SessionConstant::USER => $user]);

        return redirect('student');

    }
}
