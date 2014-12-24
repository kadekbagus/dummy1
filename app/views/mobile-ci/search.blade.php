@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
	@foreach($products as $product)
		<div class="main-theme catalogue">
			<div class="row row-xs-height catalogue-top">
				<div class="col-xs-6 catalogue-img col-xs-height col-middle coupon-wrapper">
					<div>
						<div class="ribbon-wrapper-yellow ribbon1st">
							<div class="ribbon-yellow">Promo</div>
						</div>
						<div class="ribbon-wrapper-red ribbon2nd">
							<div class="ribbon-red">New</div>
						</div>
					</div>
					<div class="zoom-wrapper">
						<div class="zoom"><a href="{{ asset($product->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
					</div>
					<a href="{{ asset($product->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product->image) }}"></a>
				</div>
				<div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
					<div class="row">
						<div class="col-xs-12">
							<h3>{{ $product->product_name }}</h3>
						</div>
						<div class="col-xs-12">
							<h4>Code : {{ $product->upc_code }}</h4>
						</div>					
						<div class="col-xs-6 price">
							<small>Starting From</small>
							<h3 class="">USD 50 </h3>
						</div>
						<div class="col-xs-6 catalogue-control price">
							<div class="circlet btn-blue pull-right">
								<img src="{{ asset('mobile-ci/images/cart-clear.png') }}" >
							</div>
						</div>
							
					</div>
				</div>
			</div>
			<div class="row catalogue-control-wrapper">
				<div class="col-xs-9 catalogue-short-des ">
					<p>{{ $product->short_description }}</p>
				</div>
				<div class="col-xs-3 catalogue-control ">
					<div class="circlet btn-blue pull-right">
						<a href="{{ url('customer/product/'.$product->product_id) }}"><img src="{{ asset('mobile-ci/images/detail-clear.png') }}" ></a>
					</div>
				</div>
			</div>
		</div>
	@endforeach
@stop

@section('ext_script_bot')
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
@stop