<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PasswordReset;
use App\Http\Requests\SendEmail;
use Exception;
use Illuminate\Support\Facades\Validator as FacadesValidator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Validator;

class ForgotPasswordController extends Controller
{

    /** 
     * Sends a new created token
     * @param  string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:100',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user)
        {
            return response()->json([
                'message' => 'we can not find a user with that email address'
            ],404);
        }
        
        $passwordReset = PasswordReset::updateOrCreate(
            ['email' => $user->email],

            [
                'email' => $user->email,
                'token' => JWTAuth::fromUser($user)
            ]
        );
        
        if ($user && $passwordReset) 
        {
            $sendEmail = new SendEmail();
            $sendEmail->sendMail($user->email,$passwordReset->token);
        }

        return response()->json(['message' => 'we have emailed your password reset link to respective mail'],205);

    }

    /**
     * Restting the Password
     * 
     * @return \Illuminate\Http\JsonResponse  
     */
    public function resetPassword(Request $request)
    {
        $validate = FacadesValidator::make($request->all(), [
            'new_password' => 'min:6|required|',
            'confirm_password' => 'required|same:new_password'
        ]);

        if ($validate->fails())
        {

            return response()->json(
                ['status' => 201, 
                 'message' => "Password doesn't match"
                ]);
        }
        
        $passwordReset = PasswordReset::where('token', $request->token)->first();


        if (!$passwordReset) 
        {
            return response()->json(['status' => 401, 'message' => 'This token is invalid']);
        }

        $user = User::where('email', $passwordReset->email)->first();

        if (!$user)
        {
            return response()->json([
                'status' => 201, 
                'message' => "we can't find the user with that e-mail address"
            ], 201);
        }
        else
        {
            $user->password = bcrypt($request->new_password);
            $user->save();
            $passwordReset->delete();
            return response()->json([
                'status' => 201, 
                'message' => 'Password reset successfull!'
            ]);
        }
    }
}