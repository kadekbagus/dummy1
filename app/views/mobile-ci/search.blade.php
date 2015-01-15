@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
	@if($data->status === 1)
		@if(sizeof($data->records) > 0)
			<div id="search-tool" style="display:block; background-color:#0aa5d5;">
			    <div class="row" style="padding:15px;">
				    <div class="col-xs-6 search-tool-col">
				    	<input type="hidden" name="keyword" value="{{ Input::get('keyword') }}">
				    	<a href="{{ url('/customer/search?keyword='.Input::get('keyword').'&sort_by=price&sort_mode=asc') }}" id="sort-by-price-up"><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-chevron-up fa-stack-1x sort-chevron"></i></span></a> <a href="{{ url('/customer/search?keyword='.Input::get('keyword').'&sort_by=price&sort_mode=desc') }}" id="sort-by-price-down"><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-chevron-down fa-stack-1x sort-chevron"></i></span></a><span class="sort-lable">IDR</span>
				    	{{-- Form::select('sort_by', array('product_name' => 'Nama', 'price' => 'Harga'), Input::get('sort_by'), array('class'=>'form-control')) --}}
				    </div>
				    <div class="col-xs-5 search-tool-col">
				    	<a href="{{ url('/customer/search?keyword='.Input::get('keyword').'&sort_by=product_name&sort_mode=asc') }}" id="sort-by-name-up"><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-chevron-up fa-stack-1x sort-chevron"></i></span></a> <a href="{{ url('/customer/search?keyword='.Input::get('keyword').'&sort_by=product_name&sort_mode=desc') }}" id="sort-by-name-down"><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-chevron-down fa-stack-1x sort-chevron"></i></span></a><span class="sort-lable">A-Z</span>
				    	{{-- Form::select('sort_mode', array('asc' => 'A-Z', 'desc' => 'Z-A'), Input::get('sort_mode'), array('class'=>'form-control')) --}}
				    </div>
				    <div class="col-xs-1 search-tool-col text-right">
						<a href="{{ url('/customer/home') }}"><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-close fa-stack-1x sort-chevron"></i></span></a>
				    </div>
			 	</div>
			</div>
			@foreach($data->records as $product)
				<div class="main-theme catalogue">
					<div class="row row-xs-height catalogue-top">
						<div class="col-xs-6 catalogue-img col-xs-height col-middle coupon-wrapper">
							<div>
								<?php $x = 1; $on_promo = false;?>
								@if(in_array($product->product_id, $promo_products))
								<div class="ribbon-wrapper-yellow ribbon{{$x}}st">
									<div class="ribbon-yellow">Promo</div>
								</div>
								<?php $on_promo = true; $x++;?>
								@endif
								@if($product->new_from <= \Carbon\Carbon::now() && $product->new_until >= \Carbon\Carbon::now())
								<div class="ribbon-wrapper-red ribbon{{$x}}nd">
									<div class="ribbon-red">New</div>
								</div>
								<?php $x++;?>
								@endif
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
									<?php $prices = array();?>
									@foreach($product->variants as $variant)
										<?php 
											$prices[] = $variant->price;
										?>
									@endforeach
									@if(count($product->variants) > 1)
									<small>Starting From</small>
									@endif
									@if($on_promo)
									<h3 class="currency"><small>IDR</small> <span class="strike">{{ min($prices) + 0 }}</span></h3>
									@else
									<h3 class="currency"><small>IDR</small> {{ min($prices) + 0 }}</h3>
									@endif
								</div>
								@if(count($product->variants) <= 1)
								<div class="col-xs-6 catalogue-control price">
									<div class="circlet btn-blue pull-right">
										<a id="addToCartButton" class="product-add-to-cart" data-product-id="{{ $product->product_id }}" data-product-variant-id="{{ $product->variants[0]->product_variant_id }}">
											<img src="{{ asset('mobile-ci/images/cart-clear.png') }}" >
										</a>
									</div>
								</div>
								@else
								<div class="col-xs-6 catalogue-control price">
									<div class="circlet btn-blue pull-right">
										<a class="product-add-to-cart" href="{{ url('customer/product?id='.$product->product_id) }}">
											<img src="{{ asset('mobile-ci/images/cart-clear.png') }}" >
										</a>
									</div>
								</div>
								@endif
									
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
	{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
	<script type="text/javascript">
		// window.onunload = function(){};
		$(window).bind("pageshow", function(event) {
		    if (event.originalEvent.persisted) {
		        window.location.reload() 
		    }
		});
		$(document).ready(function(){
			// add to cart
			$('body').on('click', '#addToCartButton', function(event){
				var prodid = $(this).data('product-id');
				var prodvarid = $(this).data('product-variant-id');
				var img = $(this).children('img');
				var cart = $('#shopping-cart');
				$.ajax({
					url: apiPath+'customer/addtocart',
					method: 'POST',
					data: {
						productid: prodid,
						productvariantid: prodvarid,
						qty:1
					}
				}).done(function(data){
					// animate cart
					
					var imgclone = img.clone().offset({
						top: img.offset().top,
						left: img.offset().left
					}).css({
						'opacity': '0.5',
						'position': 'absolute',
						'height': '20px',
						'width': '20px',
						'z-index': '100'
					}).appendTo($('body')).animate({
						'top': cart.offset().top + 10,
						'left': cart.offset().left + 10,
						'width': '10px',
						'height': '10px',
					}, 1000);

					setTimeout(function(){
						cart.effect('shake', {
							times:2,
							distance:4,
							direction:'up'
						}, 200)
					}, 1000);

					imgclone.animate({
						'width': 0,
						'height': 0
					}, function(){
						$(this).detach();
						$('.cart-qty').css('display', 'block');
					    var cartnumber = parseInt($('#cart-number').attr('data-cart-number'));
					    cartnumber = cartnumber + 1;
					    if(cartnumber <= 9){
					    	$('#cart-number').attr('data-cart-number', cartnumber);
					    	$('#cart-number').text(cartnumber);
					    }else{
					    	$('#cart-number').attr('data-cart-number', '9+');
					    	$('#cart-number').text('9+');
					    }
					});

				});
			});
		});
	</script>
@stop