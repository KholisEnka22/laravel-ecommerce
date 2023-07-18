<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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
            $product = Product::where('slug',$item['slug'])->first();

            $total_item = $product->price * $item['quantity'];
            $products[] = [
                'qty' => $item['quantity'],
                'base_price' =>  $product->price,
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
            $totalPrice =+ $total_item;
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

            $order->order_items = $products;
            $response = [
                'status' => 200,
                'message' => "success",
                'data' => $order
            ];
            
            return $response;
        } catch (\Throwable $th) {
            $response = [
                'status' => 400,
                'message' => "error",
                'errors' => $th->getMessage()
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
                'data' => $orders
            ];
        return $response;
    }
}
