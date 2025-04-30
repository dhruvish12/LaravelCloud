<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ExampleMail;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOTP;
use Illuminate\Support\Facades\Cache;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    // protected function validator(array $data)
    // {
    //     return Validator::make($data, [
    //         'name' => ['required', 'string', 'max:255'],
    //         'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
    //         'password' => ['required', 'string', 'min:8', 'confirmed'],
    //     ]);
    // }



    // protected function create(array $data)
    // {
    //     return User::create([
    //         'name' => $data['name'],
    //         'email' => $data['email'],
    //         'password' => Hash::make($data['password']),
    //     ]);
    // }

    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        // Validate Input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors(),
                'result' => (object) [],
            ], 422);
        }

        // Generate OTP
        $otp = rand(1000, 9999);

        // Store OTP and Password Hash in Cache
        Cache::put('otp_' . $request->email, [
            'otp' => $otp,
            'name' => $request->name,
            'password' => Hash::make($request->password) // Hash and store the password
        ], 300);

        // Send OTP via Email
        Mail::to($request->email)->send(new SendOTP($otp));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'result' => [
                'otp' => $otp, // You may remove this in production
            ]
        ], 200);
    }

    /**
     * Verify OTP and Create User
     */
    public function verifyOtp(Request $request)
    {
        // Validate Input
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric|digits:4',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors(),
                'result' => (object) [],
            ], 422);
        }

        // Retrieve Cached Data (OTP & Password)
        $cachedData = Cache::get('otp_' . $request->email);
        // dd($cachedData);
        if (!$cachedData || $request->otp != $cachedData['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP',
                'result' => (object) [],
            ], 400);
        }

        // Check if User Already Exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Create New User with Stored Password
            $user = User::create([
                'name' => $cachedData['name'], // Ensure name is not empty
                'email' => $request->email,
                'password' => $cachedData['password'], // Use stored hashed password
                'role_id' => 1,
            ]);
        }

        // Clear Cached OTP
        Cache::forget('otp_' . $request->email);

        // Generate Token
        $token = $user->createToken($user->id)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User verified successfully',
            'result' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'access_token' => $token,
            ]
        ], 200);
    }

    public function forgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist',
                'result' => (object) []
            ], 404);
        }

        // Generate OTP and update user record
        $otp = rand(1000, 9999);
        $user->otp = $otp;
        $user->save();

        // Send OTP via email
        $emailContent = new ExampleMail($otp);
        Mail::to($email)->send($emailContent);

        return response()->json([
            'success' => true,
            'message' => 'OTP Created successfully',
            'result' => [
                'otp' => $otp,
            ],
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users,email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 400);
        }

        $user = User::where('email', $request->input('email'))->first();

        $user->update([
            'password' => $request->input('password')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password Updated Successfully.'
        ], 200);
    }

    public function resetVerifyOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric',
        ]);

        $email = $request->input('email');
        $otp = $request->input('otp');

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'result' => (object) []
            ], 404);
        }

        if ($user->otp != $otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP',
                'result' => (object) []
            ], 400);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'result' => [
                'email' => $email,
            ],
        ]);
    }
}
