@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
	@if($data->status === 1)
		@if(sizeof($data->records) > 0)
			@foreach($data->records as $promo)
				<div class="main-theme catalogue promo-list" id="promo-{{$promo->promotion_id}}">
					<div class="row row-xs-height catalogue-top">
						<div class="col-xs-6 catalogue-img col-xs-height col-middle">
							<div class="zoom-wrapper">
								<div class="zoom"><a href="{{ asset($promo->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
							</div>
							<a href="{{ asset($promo->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($promo->image) }}"></a>
						</div>
						<div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
							<div class="row">
								<div class="col-xs-12">
									<h3>{{ $promo->promotion_name }}</h3>
								</div>
								@if($promo->promotion_type == 'product')
									<div class="col-xs-12">
									@if($promo->promotionrule->discount_object_type == 'product')
										<p class="promo-item">Product : {{ $promo->promotionrule->discountproduct->product_name }}</p>
									@elseif($promo->promotionrule->discount_object_type == 'family')
										<p class="promo-item">
											Category : 
											@if(!is_null($promo->promotionrule->discountcategory1))
											<span>{{ $promo->promotionrule->discountcategory1->category_name }}</span>
											@endif
											@if(!is_null($promo->promotionrule->discountcategory2))
											<span>{{ $promo->promotionrule->discountcategory2->category_name }}</span>
											@endif
											@if(!is_null($promo->promotionrule->discountcategory3))
											<span>{{ $promo->promotionrule->discountcategory3->category_name }}</span>
											@endif
											@if(!is_null($promo->promotionrule->discountcategory4))
											<span>{{ $promo->promotionrule->discountcategory4->category_name }}</span>
											@endif
											@if(!is_null($promo->promotionrule->discountcategory5))
											<span>{{ $promo->promotionrule->discountcategory5->category_name }}</span>
											@endif
										</p>
									@endif
									</div>
								@endif
								<div class="col-xs-12">
									<h4>{{ date('j M Y', strtotime($promo->begin_date)) }} - {{ date('j M Y', strtotime($promo->end_date)) }}</h4>
								</div>
								<div class="col-xs-6 catalogue-control text-right pull-right">
									<div class="circlet btn-blue detail-btn pull-right vertically-spaced">
										<a href="{{ url('customer/promotion?promoid='.$promo->promotion_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			@endforeach
		@else
			<div class="row padded">
				<div class="col-xs-12">
					<h4>Tidak ada promosi untuk saat ini.</h4>
				</div>
			</div>
		@endif
	@endif
@stop

@section('modals')
  
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
			if(window.location.hash){
				var hash = window.location.hash;
				var producthash = "#promo-"+hash.replace(/^.*?(#|$)/,'');
				console.log(producthash);
				var hashoffset = $(producthash).offset();
				var hashoffsettop = hashoffset.top-68;
				setTimeout(function() {
				    $(window).scrollTop(hashoffsettop);
				}, 1);
			}
			// add to cart    		
			$('body').on('click', 'a.product-add-to-cart', function(event){
				$('#hasCouponModal .modal-body p').html('');
				var prodid = $(this).data('product-id');
				var prodvarid = $(this).data('product-variant-id');
				var img = $(this).children('i');
				var cart = $('#shopping-cart');
				var hasCoupon = $(this).data('hascoupon');
				var used_coupons = [];
				var anchor = $(this);

				if(hasCoupon){
					$.ajax({
						url: apiPath+'customer/productcouponpopup',
						method: 'POST',
						data: {
							productid: prodid,
							productvariantid: prodvarid
						}
					}).done(function(data){
						if(data.status == 'success'){
					        for(var i = 0; i < data.data.length; i++){
					        	var disc_val;
					        	if(data.data[i].rule_type == 'product_discount_by_percentage') disc_val = '-' + (data.data[i].discount_value * 100) + '% off';
					        	else if(data.data[i].rule_type == 'product_discount_by_value') disc_val = '- {{ $retailer->parent->currency }} ' + parseFloat(data.data[i].discount_value) +' off';
					        	$('#hasCouponModal .modal-body p').html($('#hasCouponModal .modal-body p').html() + '<div class="row vertically-spaced"><div class="col-xs-2"><input type="checkbox" class="used_coupons" name="used_coupons" value="'+ data.data[i].issued_coupon_id +'"></div><div class="col-xs-4"><img style="width:64px;" class="img-responsive" src="'+ data.data[i].promo_image +'"></div><div class="col-xs-6">'+data.data[i].promotion_name+'<br>'+ disc_val +'</div></div>');
					        }
					        $('#hasCouponModal').modal();
				        }else{
				          	console.log(data);
				        }
					});

					$('#hasCouponModal').on('change', '.used_coupons', function($event){
						var coupon = $(this).val();
						if($(this).is(':checked')){
							used_coupons.push(coupon);
						}else{
							used_coupons = $.grep(used_coupons, function(val){
								return val != coupon;
							});
						}
					});
					
					$('#hasCouponModal').on('click', '#applyCoupon', function($event){
						$.ajax({
							url: apiPath+'customer/addtocart',
							method: 'POST',
							data: {
								productid: prodid,
								productvariantid: prodvarid,
								qty:1,
								coupons : used_coupons
							}
						}).done(function(data){
							// animate cart
							if(data.status == 'success'){
								if(data.data.available_coupons.length < 1){
									anchor.data('hascoupon', '');
								}
								$('#hasCouponModal').modal('hide');
								if(prodid){
									var imgclone = img.clone().offset({
										top: img.offset().top,
										left: img.offset().left
									}).css({
										'color': '#fff',
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
								}
							}
						});
					});

					$('#hasCouponModal').on('click', '#denyCoupon', function($event){
						$.ajax({
							url: apiPath+'customer/addtocart',
							method: 'POST',
							data: {
								productid: prodid,
								productvariantid: prodvarid,
								qty:1,
								coupons : []
							}
						}).done(function(data){
							// animate cart
							if(data.status == 'success'){
								if(data.data.available_coupons.length < 1){
									anchor.data('hascoupon', '');
								}
								$('#hasCouponModal').modal('hide');
								if(prodid){
									var imgclone = img.clone().offset({
										top: img.offset().top,
										left: img.offset().left
									}).css({
										'color': '#fff',
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
								}
							}
						});
					});
				} else {
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
							'color': '#fff',
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
				}
			});
		});
	</script>
@stop