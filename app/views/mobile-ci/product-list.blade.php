@foreach($data->records as $product)
	<div class="main-theme catalogue">
		<div class="row row-xs-height catalogue-top">
			<div class="col-xs-6 catalogue-img col-xs-height col-middle coupon-wrapper">
				<div>
					<?php $x=1; ?>
					@if(in_array($product->product_id, $promo_products))
					<div class="ribbon-wrapper-yellow ribbon{{$x}}st">
						<div class="ribbon-yellow">Promo</div>
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
						<h3 class="currency"><small>IDR</small> {{ min($prices) + 0 }}</h3>
					</div>
					
					@if(count($product->variants) <= 1)
					<div class="col-xs-6 catalogue-control price">
						<div class="circlet btn-blue pull-right">
							<a class="product-add-to-cart" data-product-id="{{ $product->product_id }}" data-product-id="">
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

<ul>
@if(! is_null($subfamilies))
	@foreach($subfamilies as $subfamily)
		<li data-family-container="{{ $subfamily->category_id }}" data-family-container-level="{{ $subfamily->category_level }}"><a class="family-a" data-family-id="{{ $subfamily->category_id }}" data-family-level="{{ $subfamily->category_level }}" data-family-isopen="0" ><div class="family-label">{{ $subfamily->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
			<div class="product-list"></div>
		</li>
	@endforeach
@endif
</ul>