@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
	@if($data->status === 1)
		@if(sizeof($data->records) > 0)
			<div id="search-tool" style="display:block; background-color:#0aa5d5;">
			    <div class="row" style="padding:15px;">
			    	{{ Form::open(array('method'=>'GET')) }}
				    <div class="col-xs-4 search-tool-col">
				    	<input type="hidden" name="keyword" value="{{ Input::get('keyword') }}">
				    	{{ Form::select('sort_by', array('product_name' => 'Nama', 'price' => 'Harga'), Input::get('sort_by'), array('class'=>'form-control')) }}
				    </div>
				    <div class="col-xs-4 search-tool-col">
				    	{{ Form::select('sort_mode', array('asc' => 'A-Z', 'desc' => 'Z-A'), Input::get('sort_mode'), array('class'=>'form-control')) }}
				    </div>
				    <div class="col-xs-4 search-tool-col">
				        <button class="form-control btn btn-success" type="submit">Go</button>
				    </div>
				    {{ Form::close() }}
			 	</div>
			</div>
			@foreach($data->records as $product)
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
									<h3 class="">IDR {{ $product->price + 0 }} </h3>
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
								<a href="{{ url('customer/product?id='.$product->product_id) }}"><img src="{{ asset('mobile-ci/images/detail-clear.png') }}" ></a>
							</div>
						</div>
					</div>
				</div>
			@endforeach
		@else
			<div class="row padded">
				<div class="col-xs-12">
					<h4>Tidak ada produk yang sesuai kriteria.</h4>
				</div>
			</div>
		@endif
	@else
		<div class="row padded">
			<div class="col-xs-12">
				<h4>Hasil pencarian terlalu banyak, tolong persempit pencarian Anda.</h4>
			</div>
		</div>
	@endif
@stop

@section('ext_script_bot')
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
@stop