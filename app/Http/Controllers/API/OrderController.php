<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseController
{
    public function checkout(Request $request)
    {
        $user = auth()->user();
        $items = $request['items'];
        $totalPrice = 0;

        $products = [];
        foreach ($items as $item) {
            $product = Product::where('slug', $item['slug'])->first();

            $total_item = $product->price * $item['quantity'];
            $products[] = [
                'qty' => $item['quantity'],
                'base_price' => $product->price,
                'base_total' => $total_item,
                'tax_amount' => 0,
                'tax_percent' => 0,
                'discount_amount' => 0,
                'discount_percent' => 0,
                'sub_total' => $total_item,
                'name' => $product->name,
                'weight' => $product->weight,
                'order_id' => null,
                'product_id' => $product->id,
            ];
            $totalPrice += $total_item;
        }

        $dataCreated = [
            'user_id' => auth()->id(),
            'code' => Order::generateCode(),
            'status' => Order::CREATED,
            'order_date' => Carbon::now(),
            'payment_due' => Carbon::now()->addHours(2),
            'payment_status' => Order::UNPAID,
            'base_total_price' => $totalPrice,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'shipping_cost' => $request['shipping_cost'],
            'grand_total' => $totalPrice + $request['shipping_cost'] + 2000,
            'customer_first_name' => $user->first_name,
            'customer_last_name' => $user->last_name,
            'customer_address1' => $user->address1,
            'customer_address2' => $user->address2,
            'customer_phone' => $user->phone,
            'customer_email' => $user->email,
            'customer_city_id' => $user->city_id,
            'customer_province_id' => $user->province_id,
            'customer_postcode' => $user->postcode,
            'note' => $request['note'],
            'shipping_courier' => $request['shipping_courier'],
            'shipping_service_name' => $request['shipping_service'],
        ];

        try {
            DB::beginTransaction();
            $order = Order::create($dataCreated);

            foreach ($products as $product) {
                $product['order_id'] = $order->id;

                OrderItem::create($product);
            }

            DB::commit();

            $this->initPaymentGateway();

            $customerDetails = [
                'first_name' => $order->customer_first_name,
                'last_name' => $order->customer_last_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ];

            $data_payment = [
                'enable_payments' => Payment::PAYMENT_CHANNELS,
                'transaction_details' => [
                    'order_id' => $order->code,
                    'gross_amount' => (int) $order->grand_total,
                ],
                'customer_details' => $customerDetails,
                'expiry' => [
                    'start_time' => date('Y-m-d H:i:s T'),
                    'unit' => \App\Models\Payment::EXPIRY_UNIT,
                    'duration' => \App\Models\Payment::EXPIRY_DURATION,
                ],
            ];

            $snap = \Midtrans\Snap::createTransaction($data_payment);

            if ($snap->token) {
                $order->payment_token = $snap->token;
                $order->payment_url = $snap->redirect_url;
                $order->save();
            }

            $shipmentParams = [
                'user_id' => auth()->user()->id,
                'order_id' => $order->id,
                'status' => Shipment::PENDING,
                'total_qty' => 0,
                'total_weight' => 0,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'address1' => $user->address1,
                'address2' => $user->address2,
                'phone' => $user->phone,
                'email' => $user->email,
                'city_id' => $user->city_id,
                'province_id' => $user->province_id,
                'postcode' => $user->postcode,
            ];

            foreach ($products as $product) {
                $shipmentParams['total_qty'] += $product['qty'];
                $shipmentParams['total_weight'] += $product['weight'] * $product['qty'];
            }

            Shipment::create($shipmentParams);

            $response = [
                'status' => 200,
                'message' => "success",
                'data' => $order,
            ];

            return $response;
        } catch (\Throwable $th) {
            DB::rollBack();

            $response = [
                'status' => 400,
                'message' => "error",
                'errors' => $th->getMessage(),
            ];

            return $response;
        }
        
    }
    public function getTransaksi()
    {
        $orders = Order::where('user_id', auth()->id())
            ->paginate(5);

        $response = [
            'status' => 200,
            'message' => "sukses",
            'data' => $orders,
        ];
        return $response;
    }
}
