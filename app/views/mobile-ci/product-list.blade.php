@foreach($data->records as $product)
	<div class="main-theme catalogue">
		<div class="row row-xs-height catalogue-top">
			<div class="col-xs-6 catalogue-img col-xs-height col-middle coupon-wrapper">
				<div>
					<?php $x = 1; $on_promo = false;?>
					@if(in_array($product->product_id, $promo_products))
					<div class="ribbon-wrapper-green ribbon{{$x}}st">
						<div class="ribbon-green">Promo</div>
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
					<!-- <pre>{{ var_dump($promotions) }}</pre> -->
					<div class="col-xs-12 price">
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
							<?php $discount=0;?>
							@foreach($promotions as $promotion)
								@if($promotion->product_id == $product->product_id)
									@if($promotion->rule_type == 'product_discount_by_percentage')
										<?php $discount = $discount + (min($prices) * $promotion->discount_value);?>
									@elseif($promotion->rule_type == 'product_discount_by_value')
										<?php $discount = $discount + $promotion->discount_value;?>
									@endif
								@endif
							@endforeach
							<h3 class="currency currency-promo"><small>IDR</small> <span class="strike">{{ min($prices) + 0 }}</span></h3>
							<h3 class="currency"><small>IDR</small> <span>{{ min($prices) - $discount + 0 }}</span></h3>
						@else
						<h3 class="currency"><small>IDR</small> {{ min($prices) + 0 }}</h3>
						@endif
					</div>
				</div>
			</div>
		</div>
		<div class="row catalogue-control-wrapper">
			<div class="col-xs-6 catalogue-short-des ">
				<p>{{ $product->short_description }}</p>
			</div>
			<div class="col-xs-2 catalogue-control text-center">
				<div class="circlet btn-blue detail-btn">
					<a href="{{ url('customer/product?id='.$product->product_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
				</div>
			</div>
			@if(count($product->variants) <= 1)
			<div class="col-xs-2 catalogue-control price ">
				<div class="circlet btn-blue cart-btn text-center">
					<a class="product-add-to-cart" data-product-id="{{ $product->product_id }}" data-product-variant-id="{{ $product->variants[0]->product_variant_id }}">
						<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
					</a>
				</div>
			</div>
			@else
			<div class="col-xs-2 catalogue-control price">
				<div class="circlet btn-blue cart-btn text-center">
					<a class="product-add-to-cart" href="{{ url('customer/product?id='.$product->product_id.'#select-attribute') }}">
						<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
					</a>
				</div>
			</div>
			@endif
		</div>
	</div>
@endforeach

<ul>
@if(! is_null($subfamilies))
	@foreach($subfamilies as $subfamily)
		<li data-family-container="{{ $subfamily->category_id }}" data-family-container-level="{{ $subfamily->category_level }}"><a class="family-a" data-family-id="{{ $subfamily->category_id }}" data-family-level="{{ $subfamily->category_level }}" data-family-isopen="0" ><div class="family-label">{{ $subfamily->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
			<div class="product-list"></div>
		</li>
	@endforeach
@endif
</ul>