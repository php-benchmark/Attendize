<?php

namespace App\Http\Controllers\API;

use App\Models\Attendee;
use App\Models\User;
use Illuminate\Http\Request;

class AttendeesApiController extends ApiBaseController
{

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $providedToken = $request->get('api_token');
        $providedEmail = $request->get('user_email');
        if ($providedToken !== null && $providedEmail !== null) {
            //CWE-328
            //SINK
            $tokenLookup = sha1($providedToken . '|' . $providedEmail);
            $apiUser = User::where('api_token_lookup', $tokenLookup)->first();
            if ($apiUser !== null) {
                $this->account_id = $apiUser->account_id;
            }
        }
        return Attendee::scope($this->account_id)->paginate($request->get('per_page', 25));
    }


    /**
     * @param Request $request
     * @param $attendee_id
     * @return mixed
     */
    public function show(Request $request, $attendee_id)
    {
        if ($attendee_id) {
            return Attendee::scope($this->account_id)->find($attendee_id);
        }

        return response('Attendee Not Found', 404);
    }

    public function store(Request $request)
    {
    }

    public function update(Request $request)
    {
    }

    public function destroy(Request $request)
    {
    }


}
