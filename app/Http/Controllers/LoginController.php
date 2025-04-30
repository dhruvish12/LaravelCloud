<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /*
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function login(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors(),
                'result' => (object) [],
            ], 422);
        }

        // Find User
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Credential does not exist in our records',
                'result' => (object) [],
            ], 401);
        }

        // Check Password
        if (!Hash::check(trim($request->password), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect password',
                'result' => (object) [],
            ], 401);
        }

        // Generate Token
        $token = $user->createToken($user->id)->plainTextToken;
        $languages = [];
        if ($user->language_id) {
            $languageIds = explode(',', $user->language_id);
            $languages = $languageIds;
        }

        $artist = [];
        if ($user->artist_id) {
            $artistIds = explode(',', $user->artist_id);
            $artist = $artistIds;
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'result' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'languages' => $languages,
                'artist' => $artist,
                'access_token' => $token,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('login');
    }
}
