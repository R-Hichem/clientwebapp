<?php

namespace App\Http\Controllers;

use App\Account;
use App\Transaction;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;

class ConnexionController extends Controller
{
    public function auth(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required'
        ], [
            'email.required' => 'Veillez saissir votre e-mail',
            'email.email' => "format de l'email incorrect",
            'password.required' => "Veillez saisir votre mot de passe",
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ["Les informations d'identification fournies sont incorrectes."]
            ]);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;
        $response = [
            'user' => $user,
            'token' => $token,
        ];

        return response($response, 201);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response('Loggedout', 200);
    }

    public function getCards(Request $request)
    {
        return $request->user()->accounts()->get();
    }

    public function addTransaction(Request $request)
    {

        $request->validate([
            'to' => 'required',
            'account_code' => 'required',
            'ammount' => 'required',
            'card_id' => 'required'
        ]);
        $card = Account::where('id', $request->card_id)->first();

        if ($card->sold < $request->ammount) {
            return response([
                'message' => 'fonds inssufissants pour completer la transaction',
            ], 403);
        }
        $account = Account::where('name', $request->account_code)->first();

        $card->sold -= $request->ammount;
        $account->sold += $request->ammount;

        $card->updated_at = now();
        $account->updated_at = now();

        $card->save();
        $account->save();

        $user_id =  $request->user()->id;
        $transaction = new Transaction();
        $transaction->user_id = $user_id;
        $transaction->account_id = $account->id;
        $transaction->ammount = $request->ammount;
        $transaction->save();
        $vendeuradress = env('VENDEURIP');
        $response = Http::post($vendeuradress, [
            'order_id' =>  request('order_id'),
            'vendeur_id' => request('vendeur_id')
        ]);
        return $response;
        return response([
            'message' => 'succcess',
        ], 200);
    }

    public function singleCard(Request $request)
    {
        $card = Account::find($request->card_id);
        return response([
            'card' => $card
        ], 200);
    }

    public function addCard(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'card_number' => 'required',
            'type' => 'required',
            'ccv' => 'required',
            'exp' => 'required',
        ],[
            'name.required' => 'Veillez saissir votre e-mail',
            'card_number.required' => "Veillez saisir le numero de votre carte",
            'type.required' => "Veillez choisir le type de votre carte",
            'ccv.required' => "Veillez saisir votre ccv",
            'exp.required' => "Veillez saisir la date d'expiration",
        ]);

        $acc = new Account();
        $acc->name = $request->name;
        $acc->user_id = $request->user()->id;
        $acc->card_number = $request->card_number;
        $acc->type = $request->type;
        $acc->ccv = $request->ccv;
        $acc->exp = $request->exp;
        $acc->sold = 800000.23;
        $acc->created_at = now();
        $acc->updated_at = now();
        $acc->save();
        return response([
            'message' => "sucess",
            'newCard' => $acc
        ], 200);
    }


    public function register(Request $request){
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ],[
            'email.required' => 'Veillez saissir votre e-mail',
            'email.email' => "format de l'email incorrect",
            'password.required' => "Veillez saisir votre mot de passe",
        ]);
       return User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
    }
}