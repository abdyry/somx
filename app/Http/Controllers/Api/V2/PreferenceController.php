<?php
/**
 * @package PreferenceController
 * @author tehcvillage <support@techvill.org>
 * @contributor Md. Abdur Rahaman <[abdur.techvill@gmail.com]>
 * @created 27-12-2022
 */

namespace App\Http\Controllers\Api\V2;
use App\Http\Resources\V2\{
    PreferenceResource,
    HelpTopicResource,
    PagesResource
    };
use App\Http\Controllers\Controller;
use App\Http\Helpers\Common;
use Illuminate\Http\{
    Request,
    JsonResponse
    };
use Illuminate\Support\Facades\Validator;
use App\Models\{
    User,
    Role,
    HelpTopic,
    Pages
    };
class PreferenceController extends Controller
{
     /**
     * @var Common;
     */
    protected $helper;
    protected $help_topic;

    /**
     * Construct the service class
     *
     * @param Common $helper
     *
     * @return void
     */
    public function __construct(Common $helper, HelpTopic $help_topic)
    {
        $this->helper = $helper;
        $this->HelpTopic = $help_topic;
    }
    /**
     * Check Login via preference
     *
     * @return JsonResponse
     */
    public function checkLoginVia()
    {
        $success['loginVia'] = settings('login_via');
        return $this->okResponse($success);
    }

    /**
     * Method by which send/request money will proceed through
     *
     * @return JsonResponse
     */
    public function checkProcessedByApi()
    {
        $success['processedBy'] = preference('processed_by');
        return $this->okResponse($success);
    }

    /**
     * Get system preferences
     *
     * @return JsonResponse
     */
    public function preferenceSettings()
    {
        return $this->okResponse(new PreferenceResource(null));
    }

    /**
     * Get custom preferences
     *
     * @return JsonResponse
     */
    public function customSetting()
    {
        $response['payment_methods']   = getPaymoneySettings('payment_methods')['mobile'];
        $response['transaction_types'] = getPaymoneySettings('transaction_types')['mobile'];
        return $this->okResponse($response);
    }
    
    public function update_fcm_token(Request $request): JsonResponse
    {
     
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        
        
        if ($validator->fails()) {
            return response()->json(['errors' => 'token cannot be empty'], 403);
        }

        $user = User::find($request->userid);

        if(isset($user)) {
            $user->fcm_token = $request->token;
            $user->save();
            return response()->json(['message' => 'FCM token successfully updated'], 200);

        } else {
            return response()->json(['message' => 'User not found'], 404);
        }
    }

    /**
     * Get user roles
     *
     * @return JsonResponse
     */
    public function userRoles()
    {
        $response['types'] = (new Role())->availableUserRoles();
        return $this->okResponse($response);
    }
    
     /**
     * @param Request $request
     * @return array
     */
    public function faq(Request $request): array
    {
        $limit = $request->has('limit') ? $request->limit : 10;
        $offset = $request->has('offset') ? $request->offset : 1;
        $helpTopics = HelpTopic::select('id', 'question', 'answer', 'ranking', 'created_at')
                        ->orderBy('ranking')->active()
                        ->orderBy("created_at", 'desc')->paginate($limit, ['*'], 'page', $offset);
        $helpTopics = HelpTopicResource::collection($helpTopics);
        return [
            'total_size' => $helpTopics->total(),
            'limit' => (int)$limit,
            'offset' => (int)$offset,
            'helpTopics' => $helpTopics->items()
        ];

    }
    
     /**
     * @param Request $request
     * @return array
     */
    public function pages(Request $request): array
    {
        $helpTopics = Pages::select('id', 'name', 'content', 'status')
                    ->orderBy('status', 'desc')
                    ->get();
        $helpTopics = PagesResource::collection($helpTopics);
        return [
            'pages' => $helpTopics
        ];
    }

    

}
