<?php

namespace App\Http\Controllers\Merchant;

use DB;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Models\Orders;
use App\Models\Listing;
use App\Models\OrderBooking;
use App\Models\MerchantOrder;
use App\Models\TableBookingOrder;
use App\Models\AppointmentBookingOrder;

use App\Http\Controllers\Controller;
use Image;
use PDF;


class ApiOrdersController extends Controller
{
    //
	public function index($user_id)
    {		
		
		$listing = Listing::where('user_id', '=', $user_id)->first();

		$return['food_orders'] = $return['product_orders'] = $return['appointment_booking_order'] = $return['table_booking_order'] = $return['merchant_order_status'] = [];
		if($listing)
		{
		  $listing_id = $listing->id;
		   
			$OrderBooking = new OrderBooking;
			$return['merchant_order_status'] = $merchant_order_status = $OrderBooking->merchant_order_status;
		   
		   $return['food_orders'] = OrderBooking::whereHas('merchant_orders', function ($query) use($listing_id, $merchant_order_status) {
								$query->where(['order_type' => 'online_order', 'listing_id' => $listing_id, 'order_status' => $merchant_order_status['new_order']['id']]);
							})->with([
								'main_order',
								'merchant_orders' => function ($query1) use($listing_id, $merchant_order_status) {
										$query1->where(['order_type' => 'online_order', 'listing_id' => $listing_id, 'order_status' => $merchant_order_status['new_order']['id']])
										->with([
												'order_details.menu_item',
												'listing'
										]);
								}                                                                                        
							])
							->orderBy('created_at', 'DESC')->get();//print_r($food_orders);exit;
			
			$return['product_orders'] = OrderBooking::whereHas('merchant_orders', function($query) use($listing_id, $merchant_order_status) {
									$query->where(['order_type' => 'online_shopping', 'listing_id' => $listing_id, 'order_status' => $merchant_order_status['new_order']['id']]);
								})->with([ 
								'main_order',
								'merchant_orders' => function ($query1) use($listing_id, $merchant_order_status) {
										$query1->where(['order_type' => 'online_shopping', 'listing_id' => $listing_id, 'order_status' => $merchant_order_status['new_order']['id']])
										->with([
												'order_details.shop_item',
												'listing'
										]);
								}                                                                                        
							])
							->orderBy('created_at', 'DESC')->get();
							
		   $return['appointment_booking_order'] = Orders::whereHas('appointment_booking', function($query) use($listing_id) {
													$query->where(['listing_id' => $listing_id, 'status' => 1]);
												})->with([
													'appointment_booking' => function ($query1) use($listing_id) {
														$query1->with([
															'listing'
														])->where(['listing_id' => $listing_id, 'status' => 1]);
													}
												])->where('appointment_booking_id', '<>', 'NULL')
												->orderBy('created_at', 'DESC')->get();
			$return['table_booking_order'] = Orders::whereHas('table_booking', function($query) use($listing_id) {
												$query->where(['listing_id' => $listing_id, 'status' => 1]);
											})->with([
												'table_booking' => function ($query2) use($listing_id) {
													$query2->with([
														'listing'
													])->where(['listing_id' => $listing_id, 'status' => 1]);
												}
											])
											->where('table_booking_id', '<>', 'NULL')
											->orderBy('created_at', 'DESC')->get();
									
			//print_r($product_orders);exit;
		}
	
			$return['status'] = 1;	
			$return['msg'] = "Success";
			return response()->json($return);
		//return $this->_loadMerchantView('orders.index', compact('food_orders', 'product_orders', 'appointment_booking_order', 'table_booking_order', 'merchant_order_status'));
    }
	
	public function pending($user_id)
	{
	
		$listing = Listing::where('user_id', '=', $user_id)->first();
		
		$return['food_orders'] = $return['product_orders'] = $return['appointment_booking_order'] = $return['table_booking_order'] = [];
		if($listing)
		{
			$listing_id = $listing->id;

			$OrderBooking = new OrderBooking;
			$return['table_booking_order'] = $merchant_order_status = $OrderBooking->merchant_order_status;
							
			$return['appointment_booking_order'] = Orders::whereHas('appointment_booking', function($query) use($listing_id) {
													$query->where(['listing_id' => $listing_id, 'status' => 0]);
												})->with([
													'appointment_booking' => function ($query1) use($listing_id) {
														$query1->with([
															'listing'
														])->where(['listing_id' => $listing_id, 'status' => 0]);
													}
												])->where('appointment_booking_id', '<>', 'NULL')
												->orderBy('created_at', 'DESC')->get();
									
			$return['table_booking_order'] = Orders::whereHas('table_booking', function($query) use($listing_id) {
													$query->where(['listing_id' => $listing_id, 'status' => 0]);
												})->with([
												'table_booking' => function ($query2) use($listing_id) {
													$query2->with([
														'listing'
													])->where(['listing_id' => $listing_id, 'status' => 0]);
												}
											])->where('table_booking_id', '<>', 'NULL')
											->orderBy('created_at', 'DESC')->get();//print_r($appointment_booking_order);exit;
		}
		$return['status'] = 1;	
			$return['msg'] = "Success";
			return response()->json($return);
	//	return $this->_loadMerchantView('orders.pending', compact('food_orders', 'product_orders', 'appointment_booking_order', 'table_booking_order'));
	}
	
	public function history($user_id)
	{
		
		$listing = Listing::where('user_id', '=', $user_id)->first();
		
		$return['food_orders'] = $return['product_orders'] = $return['merchant_order_status'] = $return['booking_order_status'] = [];
		if($listing)
		{		   
			$listing_id = $listing->id;

			$OrderBooking = new OrderBooking;
			$return['merchant_order_status'] = $merchant_order_status = $OrderBooking->merchant_order_status;
			
			$TableBookingOrder = new TableBookingOrder;
			$return['booking_order_status'] = $TableBookingOrder->order_status;
			
			$order_ids_for_history = [
				$merchant_order_status['completed']['id'],
				$merchant_order_status['cancelled']['id']
			];

			$return['food_orders'] = OrderBooking::whereHas('merchant_orders', function ($query) use($listing_id, $order_ids_for_history) {
								$query->where(['order_type' => 'online_order', 'listing_id' => $listing_id])
								->whereIn('order_status', $order_ids_for_history);
							})->with([
								'main_order',
								'merchant_orders' => function ($query1) use($listing_id, $order_ids_for_history) {
									$query1->where(['order_type' => 'online_order', 'listing_id' => $listing_id])
									->whereIn('order_status', $order_ids_for_history)
									->with([
													'order_details.menu_item',
													'listing'
											]);
									}                                                                                        
							])
							->orderBy('created_at', 'DESC')->get();
			
			$return['product_orders'] = OrderBooking::whereHas('merchant_orders', function ($query) use($listing_id, $order_ids_for_history) {
								$query->where(['order_type' => 'online_shopping', 'listing_id' => $listing_id])
								->whereIn('order_status', $order_ids_for_history);
							})->with([
								'main_order',
								'merchant_orders' => function ($query1) use($listing_id, $order_ids_for_history) {
									$query1->where(['order_type' => 'online_shopping', 'listing_id' => $listing_id])
									->whereIn('order_status', $order_ids_for_history)
									->with([
													'order_details.shop_item',
													'listing'
											]);
									}                                                                                        
							])
							->orderBy('created_at', 'DESC')->get();
			
			$table_booking_ids_for_history = $app_booking_ids_for_history = array(2,3);
			
			$return['appointment_booking_order'] = Orders::whereHas('appointment_booking', function($query) use($listing_id, $app_booking_ids_for_history) {
													$query->where(['listing_id' => $listing_id])
														->whereIn('status', $app_booking_ids_for_history);
												})->with([
													'appointment_booking' => function ($query1) use($listing_id, $app_booking_ids_for_history) {
														$query1->with([
															'listing'
														])
														->where(['listing_id' => $listing_id])
														->whereIn('status', $app_booking_ids_for_history);
													}
												])->where('appointment_booking_id', '<>', 'NULL')
												->orderBy('created_at', 'DESC')->get();
									
			$return['table_booking_order'] = Orders::whereHas('table_booking', function($query) use($listing_id, $table_booking_ids_for_history) {
													$query->where(['listing_id' => $listing_id])
													->whereIn('status', $table_booking_ids_for_history);
												})->with([
												'table_booking' => function ($query2) use($listing_id, $table_booking_ids_for_history) {
													$query2->with([
														'listing'
													])
													->where(['listing_id' => $listing_id])
													->whereIn('status', $table_booking_ids_for_history);
												}
											])->where('table_booking_id', '<>', 'NULL')
											->orderBy('created_at', 'DESC')->get();
		}
		$return['status'] = 1;	
			$return['msg'] = "Success";
			return response()->json($return);
	//	return $this->_loadMerchantView('orders.history', compact('food_orders', 'product_orders', 'booking_orders', 'merchant_order_status', 'booking_order_status'));
	}
	
	public function shop_order_detail(Request $request)
	{
		$merchant_order = MerchantOrder::with('main_order.main_order', 'order_details.shop_item')->find($request->id);
		
		if($merchant_order)
		{
			$OrderBooking = new OrderBooking;
			$order_detail_status = $OrderBooking->order_detail_status;
			unset($order_detail_status['pending']);
			$data['merchant_order'] = $merchant_order;
			$data['order_detail_status'] = $order_detail_status;
			
			
			return \Response::json(array(
				'status' => 1,
				'data' => $data,
				'msg'   => 'Success'
				));
		}
		
		return \Response::json(array(
			'status' => 0,
			'msg'   => 'Invalid Id'
			));
	}
	
	public function food_order_detail(Request $request)
	{
		$merchant_order = MerchantOrder::with('main_order.main_order', 'order_details.menu_item')->find($request->id);
		
		if($merchant_order)
		{
			$OrderBooking = new OrderBooking;
			$order_detail_status = $OrderBooking->order_detail_status;
			unset($order_detail_status['pending']);
			$data['merchant_order'] = $merchant_order;
			$data['order_detail_status'] = $order_detail_status;			
			
			return \Response::json(array(
				'status' => 1,
				'data' => $data,
				'msg'   => 'Success'
				));
		}
		
		return \Response::json(array(
			'status' => 0,
			'msg'   => 'Invalid Id'
			));
	}
	
	public function table_order_detail(Request $request)
	{
		$order = TableBookingOrder::with('main_order')->find($request->id);
		
		if($order)
		{
			$TableBookingOrder = new TableBookingOrder;
			$order_status = $TableBookingOrder->order_status;
			$data['order'] = $order;
			$data['order_status'] = $order_status;			
			
			return \Response::json(array(
				'status' => 1,
				'data' => $data,
				'msg'   => 'Success'
				));
		}
		
		return \Response::json(array(
			'status' => 0,
			'msg'   => 'Invalid Id'
			));
	}
	
	public function appointment_order_detail(Request $request)
	{
		$order = AppointmentBookingOrder::with('main_order')->find($request->id);
		
		if($order)
		{
			$AppointmentBookingOrder = new AppointmentBookingOrder;
			$order_status = $AppointmentBookingOrder->order_status;
			$data['order'] = $order;
			$data['order_status'] = $order_status;			
			
			return \Response::json(array(
				'status' => 1,
				'data' => $data,
				'msg'   => 'Success'
				));
		}
		
		return \Response::json(array(
			'status' => 0,
			'msg'   => 'Invalid Id'
			));
	}
	
	public function food_invoice($order_id)
	{
		$order = MerchantOrder::find($order_id);//print_r($order);exit;//with(['main_order','order_details.shop_item','listing'])->
		
		if($order)
		{			
			//PDF::setOptions(['dpi' => 150, 'defaultFont' => 'sans-serif']);
			$pdf = PDF::loadView('includes.food_invoice', compact('order'));
			return $pdf->download('invoice_'.$order->invoice_id.'.pdf');//$pdf->stream();
		}
		
		return view('includes.shop_invoice', compact('order'));
	}
	
	public function shop_invoice($order_id)
	{
		$order = MerchantOrder::find($order_id);//print_r($order);exit;//with(['main_order','order_details.shop_item','listing'])->
		
		if($order)
		{			
			//PDF::setOptions(['dpi' => 150, 'defaultFont' => 'sans-serif']);
			$pdf = PDF::loadView('includes.shop_invoice', compact('order'));
			return $pdf->download('invoice_'.$order->invoice_id.'.pdf');//$pdf->stream();
		}
		
		return view('includes.shop_invoice', compact('order'));
	}
}