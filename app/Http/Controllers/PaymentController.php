<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transitions;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;



class PaymentController extends Controller
{

    private function saveRequest($request) {
        $requestModel = new RequestModel();
        $requestModel->name = $request['name'];
        $requestModel->url = $request['url'];
        $requestModel->method = $request['method'];
        $requestModel->data = $request['data'];
        $requestModel->save();
    }

    /**
     * Make a request to the payment gateway
     *
     * @param string $url
     * @param array $data
     * @return void
     */
    private function makeRequest($url, $data) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $request = [
            'name' => $data['name'],
            'url' => $url,
            'method' => 'POST',
            'data' => json_encode($data),
        ];
        $this->saveRequest($request);
        Log::info('Request saved', ["response" =>$response]);
        curl_close($curl);
        return $response;
    }

    /**
     * Pay with EasyMoney
     *
     * @param integer $amount
     * @param string $currency
     * @return void
     */
    private function payEasyMoney( $amount, $currency) {
        $url = "http://localhost:3000/process";
        $data = [
            "name" => "EasyMoney",
            "amount" => floatval($amount),
            "currency" => $currency,
        ];
        $response = $this->makeRequest($url, $data);
        return $response;
    }


    /**
     * Pay with SuperWalletz
     *
     * @param integer $amount
     * @param string $currency
     * @param integer $transactionId
     * @return void
     */
    private function paySuperWalletz( $amount, $currency, $transactionId) {
        $url = "http://localhost:3003/pay";
        $data = [
            "name" => "SuperWalletz",
            "amount" => floatval($amount),
            "currency" => $currency,
            "callback_url" => route('webhook', $transactionId)
        ];
        $response = $this->makeRequest($url, $data);
        return $response;
    }

    /**
     * Recieved request from webhook
     *
     * @param Request $request
     * @param integer $id
     * @return void
     */
    public function webHookWallet(Request $request, $id)  {
        $requestModel = RequestModel::find($id);
        if (!$requestModel) {
            return response()->json(['message' => 'Request not found'], 404);
        }
        $requestModel->status = $request->status;
        $requestModel->data = $request->all();
        $requestModel->save();
        Log::info('Webhook received', ['request' => $request->all()]);
        return response()->json(['message' => 'Webhook received']);
    }



    public function save(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'pay-method' => 'required|string|max:255',
                'amount' => 'required|numeric',
                'currency' => 'required|string|max:255',
            ]);

            if ($validatedData == null) {
                return redirect()->route('/')->with('error', 'Invalid payment method');
            }

            // Guardar los datos en la base de datos
            DB::beginTransaction();
            $transition = new Transitions();
            $transition->type_payment = $request['pay-method'];
            $transition->amount = $request->amount;
            $transition->status = 'pending';
            $transition->currency = $request->currency;
            $transition->save();

            if ($request['pay-method'] == 'easymoney') {
                $resultResponse = $this->payEasyMoney($transition->amount, $transition->currency);
                if ($resultResponse) {
                    $transition->status = $resultResponse;
                    $transition->save();
                }
            } else if ($request['pay-method'] == 'superwalletz' ){
                $resultResponse = $this->paySuperWalletz($transition->amount, $transition->currency, $transition->id);
            }

            DB::commit(); # commit transaction if successful

            return redirect()->route('home')->with('success', "Payment N° $transition->id processed $resultResponse");
        } catch (\Throwable $th) {
            Log::error('Error processing transaction', ["error" => $th]);
            DB::rollBack();
            return redirect()->route('home')->with('error', 'An error occurred while processing the payment');
        }

    }
}
