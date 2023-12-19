<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SendBotController extends Controller
{
    protected $telegramToken = "6405720883:AAE-UQqFyA1qCTBCgsQg5L9ytHxpjP6XwPg";

    public function sendCodeToGroup(Request $request) {
        // Валидация номера телефона
        $request->validate([
            'phone_number' => 'required',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email'
        ]);

        $phone_number = $request->phone_number;
        $name = $request->name;
        $email = $request->email;
        // Генерация кода
        $code = rand(1000, 9999);

        // Проверка наличия пользователя с таким номером телефона
        $user = User::where('phone_number', $phone_number)->first();

        Log::info("Received phone number: " . $request->phone_number);

        $status = 'Auth';  // По умолчанию устанавливаем статус "Auth"

        if (!$user) {
            // Если пользователь не найден, меняем статус на "Register"
            $status = 'Register';

            // Создание пользователя
            $user = User::create([
                'name' => $name,
                'phone_number' => $phone_number,
                'password' => bcrypt(Str::random(10)),
                'email' => $email,
                //'is_verified' => false
            ]);
        }

        // Сохранение кода в таблице verification_codes
        VerificationCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10)
        ]);

        // Отправка кода в Telegram группу
        $telegramGroupId = "-707651442";
        $message = "Your verification code for {$phone_number} is: {$code}";
        $this->sendTelegramMessage($message, $telegramGroupId);

        return response()->json([
            'message' => 'Code sent',
            'status' => $status  // Возвращает статус "Auth" или "Register"
        ]);
    }

    public function verifyCode(Request $request) {
        $data = $request->validate([
            'phone_number' => 'required',
            'code' => 'required'
        ]);

        // Проверка кода
        $verification = VerificationCode::where('code', $data['code'])
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }

        // Удаление кода из таблицы verification_codes, так как он больше не нужен
        $verification->delete();

        // Завершение процесса верификации или продолжение регистрации/входа
        return response()->json(['message' => 'Code verified successfully']);
    }

    private function sendTelegramMessage($message, $chatId) {
        if (empty(trim($message))) {
            throw new \InvalidArgumentException("Message text cannot be empty.");
        }

        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendMessage";

        $data = array(
            'chat_id' => $chatId,
            'text' => $message
        );

        $options = array(
            'http' => array(
                'method'  => 'POST',
                'content' => http_build_query($data),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            )
        );

        $context  = stream_context_create($options);
        file_get_contents($url, false, $context);
    }
}
