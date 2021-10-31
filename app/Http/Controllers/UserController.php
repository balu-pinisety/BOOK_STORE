<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use App\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\PasswordReset;
use App\Http\Requests\SendEmailRequest;
use Exception;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class UserController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Login user by validating Credentials
     * Get a JWT via given credentials.
     * @return \Illuminate\Http\JsonResponse
    */
    public function login(Request $request)
    {
    	$validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) 
        {
            Log::warning('Given Invalid Credentials to login');
            return response()->json($validator->errors(), 400);
        }

        Cache::remember('users', 1, function () {
            return User::all();
        });

        $user = User::where('email', $request->email)->first();

        if(!$user)
        {
            Log::alert('Unregistered Mail given for Login',['Email'=>$request->email]);
            return response()->json([
                'message' => 'we can not find the user with that e-mail address'
            ], 401);
        }

        if (!$token = auth()->attempt($validator->validated()))
        {
            Log::alert('Wrong Password given for Login',['Email'=>$request->email]);
            return response()->json(['error' => 'Incorrect Password'], 404);
        }

        Log::info('User Logged in',['Email'=>$request->email]);

        return response()->json([ 
            'message' => 'Login successfull',  
            'access_token' => $token
        ],200);
    }

    /**
     * Register a User by validating details
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|between:2,20',
            'lastname' => 'required|string|between:2,20',
            'email' => 'required|string|email|max:100',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|same:password'
        ]);

        if($validator->fails())
        {
            Log::warning('Given Invalid Credentials for register');
            return response()->json($validator->errors()->toJson(), 400);
        }

        Cache::remember('users', 1, function () {
            return User::all();
        });

        $user = User::where('email', $request->email)->first();
        
        if ($user)
        {
            Log::alert('Existing Mail given for Register',['Email'=>$request->email]);
            return response()->json(['message' => 'The email has already been taken'],401);
        }

        $user = User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            ]);

        Log::info('New user Regitered',['Email'=>$request->email]);
        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user
        ], 201);
    }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
    **/
    public function logout(Request $request) {
        auth()->logout();
        Log::info('User Logged Out',['Email'=>$request->email]);
        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request) {
        Log::info('User Refreshes For Token',['Email'=>$request->email]);
        return $this->createNewToken(auth()->refresh());
    }

    /**
     * Get the authenticated User Details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile(Request $request) {
        Log::info('User Account Details',['Email'=>$request->email]);
        return response()->json(auth()->user());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
    */
    protected function createNewToken($token)
    {
        return response()->json([
            'access_token' => $token,
        ]);
    }

}