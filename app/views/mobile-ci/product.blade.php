@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<!-- product -->
<div class="row product">
	<div class="col-xs-12 product-img">
		<!-- <div ng-include="product.ribbon"></div> -->
		<div>
			<?php $x=1; ?>
			@if(count($promotions)>0)
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
						<small>IDR</small> {{ $promotion->discount_value + 0 }}
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
	</div>
	<!-- <pre>{{ var_dump($product->attribute1) }}</pre> -->
	<div class="col-xs-12 product-attributes">
		<div class="row">
			@if(! is_null($product->attribute1))
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<ul><h5>{{ $product->attribute1->product_attribute_name }}</h5>
						<?php $attr_val = array();?>
						@foreach($attributes as $attribute)
						@if($attribute->attr1 === $product->attribute1->product_attribute_name && !in_array($attribute->value1, $attr_val))
				        <li><input type="radio" name="size" value="{{$attribute->value1}}" ><span class="attribute-title">{{ $attribute->value1 }}</span></li>
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
					<ul><h5>{{ $product->attribute2->product_attribute_name }}</h5>
						<?php $attr_val = array();?>
						@foreach($attributes as $attribute)
						@if($attribute->attr2 === $product->attribute2->product_attribute_name && !in_array($attribute->value2, $attr_val))
				        <li><input type="radio" name="size" value="{{$attribute->value2}}" ><span class="attribute-title">{{ $attribute->value2 }}</span></li>
				        <?php $attr_val[] = $attribute->value1;?>
				        @endif
				        @endforeach
				    </ul>
				</div>
			</div>
			@endif
			<!-- <div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<ul><h5>Colors</h5>
				        <li><input type="radio" name="color" value="Red" ><span class="attribute-title">Red</span></li>
				        <li><input type="radio" name="color" value="Magenta" ><span class="attribute-title">Magenta</span></li>
				        <li><input type="radio" name="color" value="Blue" ><span class="attribute-title">Blue</span></li>
				        <li><input type="radio" name="color" value="Green" ><span class="attribute-title">Green</span></li>
				        <li><input type="radio" name="color" value="Grey" ><span class="attribute-title">Gray</span></li>
				    </ul>
				</div>
			</div>
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<ul><h5>Sleeve</h5>
				        <li><input type="radio" name="sleeve" value="Long Sleeve" ><span class="attribute-title">Long Sleeve</span></li>
				        <li><input type="radio" name="sleeve" value="Short Sleeve" ><span class="attribute-title">Short Sleeve</span></li>
				    </ul>
				</div>
			</div> -->
			<!-- <div class="col-xs-12">
				<table>
					<thead>
						<tr>
							<td>Size</td>
							<td>Color</td>
							<td>Sleeve</td>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><input type="radio" name="size" value="S" > S</td>
							<td><input type="radio" name="color" value="Red" > Red</td>
							<td><input type="radio" name="sleeve" value="Long Sleeve" > Long Sleeve</td>
						</tr>
					</tbody>
				</table>
			</div> -->
		</div>
	</div>
	<div class="col-xs-12 product-bottom main-theme ">
		<div class="row">
			<div class="col-xs-6">
				<h4>Code : {{ $product->upc_code }}</h4>
			</div>
		</div>
		<div class="row">
			@if(count($promotions)>0)
			<div class="col-xs-6 strike">
				<h3 class="currency"><small>IDR</small> {{ $product->price + 0 }}</h3>
			</div>
			<div class="col-xs-6 pull-right">
				<h3 class="currency"><small>IDR</small> {{ $product->price - ($product->price * $promotion->discount_value) + 0 }}</h3>
			</div>
			@else
			<div class="col-xs-6 pull-right">
				<h3 class="currency"><small>IDR</small> {{ $product->price + 0 }}</h3>
			</div>
			@endif
		</div>
		<div class="row text-center product-control">
			<!-- <div class="col-xs-2 col-xs-offset-8 col-ms-1 col-ms-offset-10 col-md-1 col-md-offset-10 col-sm-1 col-sm-offset-10 col-lg-1 col-lg-offset-10"> -->
			<div class="col-xs-2  col-ms-1  col-md-1  col-sm-1  col-lg-1">
				<div class="circlet btn-blue" id="backBtnProduct">
					<img src="{{ asset('mobile-ci/images/back-clear.png') }}" >
				</div>
			</div>
			<div class="col-xs-2 col-ms-1 col-md-1 col-sm-1 col-lg-1 pull-right">
				<div class="circlet btn-blue pull-right">
					<img src="{{ asset('mobile-ci/images/cart-clear.png') }}" >
				</div>
			</div>
		</div>
	</div>
</div>
<!-- end of product -->
@stop

@section('ext_script_bot')
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
	<script type="text/javascript">
		$('#backBtnProduct').click(function(){
		    window.history.back()
		});
	</script>
@stop