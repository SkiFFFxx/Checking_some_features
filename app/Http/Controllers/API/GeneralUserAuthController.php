<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class GeneralUserAuthController extends Controller
{
    /**
     * Create User
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUser(Request $request)
    {
        try {
            //Validated
            $validateUser = Validator::make($request->all(),
                [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email',
                    'password' => 'required'
                ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'User Created Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Login The User
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginUser(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(),
                [
                    'email' => 'required|email',
                    'password' => 'required'
                ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            if(!Auth::attempt($request->only(['email', 'password']))){
                return response()->json([
                    'status' => false,
                    'message' => 'Email & Password does not match with our record.',
                ], 401);
            }
            //  строки для дебага

            if (!Auth::check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'User is not authenticated after attempt.',
                ], 401);
            }


            $user = User::where('email', $request->email)->first();

            return response()->json([
                'status' => true,
                'message' => 'User Logged In Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function authenticateWithPhone(Request $request)
    {
        Log::info('Is user already authenticated? ', ['authenticated' => Auth::check()]);
        Log::info('Attempting to log in with credentials: ', $request->only(['email', 'password']));
        $credentials = $request->only(['email', 'password']);
        if (Auth::attempt($credentials)) {
            Log::info('Authentication successful');
            Log::info('Logged in user: ', ['user' => Auth::user()]);
            //return redirect()->route('private.page');
        }


        Log::warning('Authentication failed. Checking if user exists.');

        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            Log::warning('No user found with the provided email.');
        } else {
            Log::warning('User found, but password does not match.');
        }

        return back()->withErrors(['email' => 'Email или пароль неверны']);
    }

    public function logout() {
        Auth::logout();
        return redirect()->route('login');
    }


    public function loginWithPhone(Request $request)
    {

        try {
            $validateUser = Validator::make($request->all(),
                [
                    'phone_number' => 'required',
                    'code' => 'required'
                ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            $verification = VerificationCode::where('code', $request->code)
                ->where('expires_at', '>', now())
                ->first();

            if (!$verification) {
                return response()->json(['message' => 'Invalid or expired code'], 400);
            }

            $user = User::where('phone_number', $request->phone_number)->first();

            if (!$user) {
                Log::info('User not found with given email: ', ['email' => $request->email]);
                return response()->json(['message' => 'User not found'], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'User Logged In Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }





    public function getUserProfile(Request $request) {
        $user = $request->user();  // Это получение текущего аутентифицированного пользователя благодаря Laravel Sanctum

        // Вернуть профиль пользователя в ответе
        return response()->json([
            'status' => true,
            'profile' => $user
        ], 200);
    }



    public function updateUserInfo(Request $request)
    {
        try {
            Log::info('updateUserInfo called with data:', $request->all());
            $user = $request->user(); // Получаем текущего аутентифицированного пользователя
            Log::info('User attempting to update:', ['user_id' => $user->id]);

            // Валидация
            $validateData = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',  // добавляем условие string и, возможно, max
                'email' => 'sometimes|required|email|string|max:255|unique:users,email,' . $user->id,  // добавляем условие string и max
            ]);

            if ($validateData->fails()) {
                Log::error('Validation error:', $validateData->errors()->all());
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validateData->errors()
                ], 422);
            }

            // Обновление информации пользователя
            $user->update($request->only(['name', 'email']));

            Log::info('User info updated:', ['user_id' => $user->id, 'new_data' => $request->only(['name', 'email'])]);

            return response()->json([
                'status' => true,
                'message' => 'User info updated successfully',
                'user' => $user
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error in updateUserInfo:', ['error_message' => $th->getMessage(), 'user_data' => $request->all()]);
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
