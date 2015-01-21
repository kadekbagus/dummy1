@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<!-- product -->
<div class="row product">
	<div class="col-xs-12 @if(count($couponstocatchs)>0) {{ 'coupon-wrapper' }} @endif product-img">
		<!-- <div ng-include="product.ribbon"></div> -->
		<div>
			<?php $x=1; ?>
			@if(count($promotions)>0)
			<div class="ribbon-wrapper-green ribbon{{$x}}st">
				<div class="ribbon-green">Promo</div>
			</div>
			<?php $x++;?>
			@endif
			@if($product->new_from <= \Carbon\Carbon::now() && $product->new_until >= \Carbon\Carbon::now())
			<div class="ribbon-wrapper-red ribbon{{$x}}nd">
				<div class="ribbon-red">New</div>
			</div>
			<?php $x++;?>
			@endif
		</div>
		<div class="zoom-wrapper">
			<div class="zoom"><a href="{{ asset($product->image) }}" data-featherlight="image"><img alt="" src="{{ asset('mobile-ci/images/product-zoom.png') }}" ></a></div>
		</div>
		<a href="{{ asset($product->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product->image) }}"></a>
	</div>
	<div class="col-xs-12 main-theme product-detail">
		<div class="row">
			<div class="col-xs-12">
				<h3>{{ $product->product_name }}</h3>
			</div>
			<div class="col-xs-12">
				<p>{{ $product->long_description }}</p>
			</div>
		</div>
		@if(count($promotions)>0)
		@foreach($promotions as $promotion)
		<div class="additional-detail">
			<div class="row">
				<div class="col-xs-12">
					<h3>Promotion Discount</h3>
				</div>
				<div class="col-xs-3">
					<p>
						@if($promotion->rule_type === 'product_discount_by_percentage')
						{{ $promotion->discount_value * 100 + 0 }}%
						@else
						<small>{{ $retailer->parent->currency_symbol }}</small> {{ $promotion->discount_value + 0 }}
						@endif
					</p>
				</div>
				<div class="col-xs-9">
					<p>{{ $promotion->promotion_name }}</p>
				</div>
			</div>
			<div class="row additional-dates">
				<div class="col-xs-5 col-sm-3">
					<p>{{ date('j M Y', strtotime($promotion->begin_date)) }}</p>
				</div>
				<div class="col-xs-2">
					<p>to</p>
				</div>
				<div class="col-xs-5 col-sm-3">
					<p>{{ date('j M Y', strtotime($promotion->end_date)) }}</p>
				</div>
			</div>
		</div>
		@endforeach
		@endif

		@if(count($couponstocatchs)>0)
		@foreach($couponstocatchs as $couponstocatch)
		<div class="additional-detail">
			<div class="row">
				<div class="col-xs-12">
					<h3>Dapatkan Kupon</h3>
				</div>
				<div class="col-xs-3">
					<p>
						@if($couponstocatch->rule_type === 'product_discount_by_percentage')
						{{ $couponstocatch->discount_value * 100 + 0 }}%
						@else
						<small>{{ $retailer->parent->currency_symbol }}</small> {{ $couponstocatch->discount_value + 0 }}
						@endif
					</p>
				</div>
				<div class="col-xs-9">
					<p>{{ $couponstocatch->promotion_name }}</p>
				</div>
			</div>
			<div class="row additional-dates">
				<div class="col-xs-5 col-sm-3">
					<p>{{ date('j M Y', strtotime($couponstocatch->begin_date)) }}</p>
				</div>
				<div class="col-xs-2">
					<p>to</p>
				</div>
				<div class="col-xs-5 col-sm-3">
					<p>{{ date('j M Y', strtotime($couponstocatch->end_date)) }}</p>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12">
					<p>{{ $couponstocatch->description }}</p>
				</div>
			</div>
		</div>
		@endforeach
		@endif
	</div>
	<!-- <pre>{{ var_dump($product->attribute1) }}</pre> -->
	<div class="col-xs-12 product-attributes" id="select-attribute">
		<div class="row">
			@if(! is_null($product->attribute1))
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<h5>{{ $product->attribute1->product_attribute_name }}</h5>
					<ul id="attribute1">
						<?php $attr_val = array();?>
						@foreach($attributes as $attribute)
						@if($attribute->attr1 === $product->attribute1->product_attribute_name && !in_array($attribute->value1, $attr_val))
				        <li><input type="radio" data-attr-lvl="1" class="attribute_value_id" name="product_attribute_value_id1" value="{{$attribute->attr_val_id1}}" ><span class="attribute-title">{{ $attribute->value1 }}</span></li>
				        <?php $attr_val[] = $attribute->value1;?>
				        @endif
				        @endforeach
				    </ul>
				</div>
			</div>
			@endif
			@if(! is_null($product->attribute2))
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<h5>{{ $product->attribute2->product_attribute_name }}</h5>
					<ul id="attribute2">
						
				    </ul>
				</div>
			</div>
			@endif
			@if(! is_null($product->attribute3))
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<h5>{{ $product->attribute3->product_attribute_name }}</h5>
					<ul id="attribute3">
						
				    </ul>
				</div>
			</div>
			@endif
		</div>
		<div class="row">
			@if(! is_null($product->attribute4))
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<h5>{{ $product->attribute4->product_attribute_name }}</h5>
					<ul id="attribute4">
						
				    </ul>
				</div>
			</div>
			@endif
			@if(! is_null($product->attribute5))
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<h5>{{ $product->attribute5->product_attribute_name }}</h5>
					<ul id="attribute5">
						
				    </ul>
				</div>
			</div>
			@endif
		</div>
	</div>
	<div class="col-xs-12 product-bottom main-theme ">
		<div class="row">
			<div class="col-xs-6">
				<h4>Code : {{ $product->upc_code }}</h4>
			</div>
		</div>
		<div class="row" id="starting-from">
			<div class="col-xs-12 text-right">
				<p><small>Starting from :</small></p>
			</div>
		</div>
		<div class="row">
			<?php $discount=0;?>
			@if(count($promotions)>0)
				@foreach($promotions as $promotion)
					@if($promotion->rule_type == 'product_discount_by_percentage')
						<?php $discount = $discount + ($product->price * $promotion->discount_value);?>
					@elseif($promotion->rule_type == 'product_discount_by_value')
						<?php $discount = $discount + $promotion->discount_value;?>
					@endif
				@endforeach
				<div class="col-xs-6 strike" id="price-before">
					<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span>{{ $product->price + 0 }}</span></h3>
				</div>
				<div class="col-xs-6 pull-right text-right" id="price">
					<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span>{{ $product->price - $discount + 0 }}</span></h3>
				</div>
			@else
			<div class="col-xs-6 pull-right text-right" id="price">
				<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span>{{ $product->price + 0 }}</span></h3>
			</div>
			@endif
		</div>
		<div class="row text-center product-control">
			<!-- <div class="col-xs-2 col-xs-offset-8 col-ms-1 col-ms-offset-10 col-md-1 col-md-offset-10 col-sm-1 col-sm-offset-10 col-lg-1 col-lg-offset-10"> -->
			<div class="col-xs-2  col-ms-1  col-md-1  col-sm-1  col-lg-1">
				<div class="circlet back-btn btn-blue" id="backBtnProduct">
					<span class="link-spanner"></span><i class="fa fa-mail-reply"></i>
				</div>
			</div>
			<div class="col-xs-2 col-ms-1 col-md-1 col-sm-1 col-lg-1 pull-right">
				<div class="circlet cart-btn btn-blue pull-right add-to-cart-button btn-disabled">
					<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- end of product -->
@stop

@section('ext_script_bot')
	{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
	<script type="text/javascript">
		var indexOf = function(needle) {
		    if(typeof Array.prototype.indexOf === 'function') {
		        indexOf = Array.prototype.indexOf;
		    } else {
		        indexOf = function(needle) {
		            var i = -1, index = -1;

		            for(i = 0; i < this.length; i++) {
		                if(this[i] === needle) {
		                    index = i;
		                    break;
		                }
		            }

		            return index;
		        };
		    }

		    return indexOf.call(this, needle);
		};
		var variants = {{ json_encode($product->variants) }};
		var promotions = {{ json_encode($promotions) }};
		var attributes = {{ json_encode($attributes) }};
		var product = {{ json_encode($product) }}
		var itemReady = [];
		$(document).ready(function(){
			if(variants.length < 2){
				var itemReady = [];
				itemReady = variants;
				$('.add-to-cart-button').removeClass('btn-disabled').attr('id', 'addToCartButton');
				var pricebefore = parseFloat(itemReady[0].price);
				var priceafter;
				if(promotions.length < 1){
					priceafter = pricebefore;
				} else {
					// console.log(promotions);
					var discount=0;
					for(var i=0;i<promotions.length;i++){
						if(promotions[i].rule_type == 'product_discount_by_percentage'){
							discount = discount + (itemReady[0].price * parseFloat(promotions[i].discount_value));
						}else if(promotions[i].rule_type == 'product_discount_by_value'){
							discount = discount + parseFloat(promotions[i].discount_value);
						}
					}
					priceafter = itemReady[0].price - discount;
					console.log(priceafter);
				}
				$('#starting-from').hide();
				$('#price-before span').text(pricebefore);
				$('#price span').text(priceafter);
			}
			var selectedVariant = {};
			var selectedLvl, selectedVal;
			selectedVariant.attr1 = undefined;
			selectedVariant.attr2 = undefined;
			selectedVariant.attr3 = undefined;
			selectedVariant.attr4 = undefined;
			selectedVariant.attr5 = undefined;
			$('.product-attributes').on('change', '.attribute_value_id', function($e){
				selectedVal = $(this).val();
				selectedLvl = $(this).data('attr-lvl');
				var attrArr = [];
				var filteredAttr = $.grep(attributes, function(n, i){
					switch(selectedLvl){
						case 1:
							selectedVariant.attr1 = selectedVal;
							selectedVariant.attr2 = undefined;
							selectedVariant.attr3 = undefined;
							selectedVariant.attr4 = undefined;
							selectedVariant.attr5 = undefined;
							return n.attr_val_id1 == selectedVal;
						case 2:
							selectedVariant.attr2 = selectedVal;
							selectedVariant.attr3 = undefined;
							selectedVariant.attr4 = undefined;
							selectedVariant.attr5 = undefined;
							return n.attr_val_id2 == selectedVal;
						case 3:
							selectedVariant.attr3 = selectedVal;
							selectedVariant.attr4 = undefined;
							selectedVariant.attr5 = undefined;
							return n.attr_val_id3 == selectedVal;
						case 4:
							selectedVariant.attr4 = selectedVal;
							selectedVariant.attr5 = undefined;
							return n.attr_val_id4 == selectedVal;
						case 5:
							selectedVariant.attr5 = selectedVal;
							return n.attr_val_id5 == selectedVal;
					}
				});
				for(var i= selectedLvl+1;i<=5;i++){
					$('#attribute'+i).html('');
				}
				for(var i=0; i<filteredAttr.length; i++){
					switch(selectedLvl){
						case 1:
							if(indexOf.call(attrArr, filteredAttr[i].attr_val_id2) < 0){
								$('#attribute'+ (selectedLvl+1)).append('<li><input type="radio" data-attr-lvl="'+ (selectedLvl+1) +'"  class="attribute_value_id" name="product_attribute_value_id'+ (selectedLvl+1) +'" value="'+ filteredAttr[i].attr_val_id2 +'" ><span class="attribute-title">'+ filteredAttr[i].value2 +'</span></li>')
								attrArr.push(filteredAttr[i].attr_val_id2);
							}
							break;
						case 2:
							if(indexOf.call(attrArr, filteredAttr[i].attr_val_id3) < 0){
								$('#attribute'+ (selectedLvl+1)).append('<li><input type="radio" data-attr-lvl="'+ (selectedLvl+1) +'"  class="attribute_value_id" name="product_attribute_value_id'+ (selectedLvl+1) +'" value="'+ filteredAttr[i].attr_val_id3 +'" ><span class="attribute-title">'+ filteredAttr[i].value3 +'</span></li>')
								attrArr.push(filteredAttr[i].attr_val_id3);
							}
							break;
						case 3:
							if(indexOf.call(attrArr, filteredAttr[i].attr_val_id4) < 0){
								$('#attribute'+ (selectedLvl+1)).append('<li><input type="radio" data-attr-lvl="'+ (selectedLvl+1) +'"  class="attribute_value_id" name="product_attribute_value_id'+ (selectedLvl+1) +'" value="'+ filteredAttr[i].attr_val_id4 +'" ><span class="attribute-title">'+ filteredAttr[i].value4 +'</span></li>')
								attrArr.push(filteredAttr[i].attr_val_id4);
							}
							break;
						case 4:
							if(indexOf.call(attrArr, filteredAttr[i].attr_val_id5) < 0){
								$('#attribute'+ (selectedLvl+1)).append('<li><input type="radio" data-attr-lvl="'+ (selectedLvl+1) +'"  class="attribute_value_id" name="product_attribute_value_id'+ (selectedLvl+1) +'" value="'+ filteredAttr[i].attr_val_id5 +'" ><span class="attribute-title">'+ filteredAttr[i].value5 +'</span></li>')
								attrArr.push(filteredAttr[i].attr_val_id5);
							}
							break;
					}
				}
				
				itemReady = $.grep(variants, function(n, i){
					return (n.product_attribute_value_id1 == selectedVariant.attr1) && (n.product_attribute_value_id2 == selectedVariant.attr2) && (n.product_attribute_value_id3 == selectedVariant.attr3) && (n.product_attribute_value_id4 == selectedVariant.attr4) && (n.product_attribute_value_id5 == selectedVariant.attr5);
				});
				var pricebefore, priceafter;
				if(itemReady.length > 0){
					// console.log(itemReady);
					pricebefore = parseFloat(itemReady[0].price);
					$('.add-to-cart-button').removeClass('btn-disabled').attr('id', 'addToCartButton');
					$('#starting-from').hide();
					if(promotions.length < 1){
						priceafter = pricebefore;
					} else {
						// get first promotions value
						var discount=0;
						for(var i=0;i<promotions.length;i++){
							if(promotions[i].rule_type == 'product_discount_by_percentage'){
								discount = discount + (itemReady[0].price * parseFloat(promotions[i].discount_value));
							}else if(promotions[i].rule_type == 'product_discount_by_value'){
								discount = discount + parseFloat(promotions[i].discount_value);
							}
						}
						priceafter = itemReady[0].price - discount;
					}
				}else{
					pricebefore = parseFloat(product.price);
					if(promotions.length < 1){
						priceafter = pricebefore;
					} else {
						// get first promotions value
						var discount=0;
						for(var i=0;i<promotions.length;i++){
							if(promotions[i].rule_type == 'product_discount_by_percentage'){
								discount = discount + (pricebefore * parseFloat(promotions[i].discount_value));
							}else if(promotions[i].rule_type == 'product_discount_by_value'){
								discount = discount + parseFloat(promotions[i].discount_value);
							}
						}
						priceafter = pricebefore - discount;
					}
					$('#starting-from').show();
					$('.add-to-cart-button').addClass('btn-disabled').removeAttr('id');
				}
				$('#price-before span').text(pricebefore);
				$('#price span').text(priceafter);
			});
			
			$('#backBtnProduct').click(function(){
			    window.history.back()
			});

			$('body').on('click', '#addToCartButton', function($event){
				// add to cart
				var prodid = itemReady[0].product_id;
				var prodvarid = itemReady[0].product_variant_id;
				var img = $(this).children('i');
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
			});
		});
	</script>
@stop